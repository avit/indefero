<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Main application to serve git repositories through a restricted SSH
 * access.
 */
class IDF_Plugin_SyncGit_Serve
{
    /**
     * Regular expression to match the path in the git command.
     */
    public $preg = '#^\'/*(?P<path>[a-zA-Z0-9][a-zA-Z0-9@._-]*(/[a-zA-Z0-9][a-zA-Z0-9@._-]*)*)\'$#';

    public $commands_readonly = array('git-upload-pack', 'git upload-pack');
    public $commands_write = array('git-receive-pack', 'git receive-pack');

    /**
     * Serve a git request.
     *
     * @param string Username.
     * @param string Command to be run.
     */
    public function serve($username, $cmd)
    {
        if (false !== strpos($cmd, "\n")) {
            throw new Exception('Command may not contain newline.');
        }
        $splitted = preg_split('/\s/', $cmd, 2);
        if (count($splitted) != 2) {
            throw new Exception('Unknown command denied.');
        }
        if ($splitted[0] == 'git') {
            $sub_splitted = preg_split('/\s/', $splitted[1], 2);
            if (count($sub_splitted) != 2) {
                throw new Exception('Unknown command denied.');
            }
            $verb = sprintf('%s %s', $splitted[0], $sub_splitted[0]);
            $args = $sub_splitted[1];
        } else {
            $verb = $splitted[0];
            $args = $splitted[1];
        }
        if (!in_array($verb, $this->commands_write) 
            and !in_array($verb, $this->commands_readonly)) {
            throw new Exception('Unknown command denied.');
        }
        if (!preg_match($this->preg, $args, $matches)) {
            throw new Exception('Arguments to command look dangerous.');
        }
        $path = $matches['path'];
        // Check read/write rights
        $new_path = $this->haveAccess($username, $path, 'writable');
        if ($new_path == false) {
            $new_path = $this->haveAccess($username, $path, 'readonly');
            if ($new_path == false) {
                throw new Exception('Repository read access denied.');
            }
            if (in_array($verb, $this->commands_write)) {
                throw new Exception('Repository write access denied.');
            }
        }
        list($topdir, $relpath) = $new_path;
        $repopath = sprintf('%s.git', $relpath);
        $fullpath = $topdir.DIRECTORY_SEPARATOR.$repopath;
        if (!file_exists($fullpath)
            and in_array($verb, $this->commands_write)) {
            // it doesn't exist on the filesystem, but the
            // configuration refers to it, we're serving a write
            // request, and the user is authorized to do that: create
            // the repository on the fly
            $p = explode(DIRECTORY_SEPARATOR, $fullpath);
            $mpath = implode(DIRECTORY_SEPARATOR, array_slice($p, 0, -1));
            mkdir($mpath, 0750, true);
            $this->initRepository($fullpath);
            $this->setGitExport($relpath, $fullpath);
        }
        $new_cmd = sprintf("%s '%s'", $verb, $fullpath);
        return $new_cmd;
    }

    /**
     * Main function called by the serve script.
     */
    public static function main($argv, $env)
    {
        if (count($argv) != 2) {
            self::fatalError('Missing argument USER.');
        }
        $username = $argv[1];
        umask(0022);
        if (!isset($env['SSH_ORIGINAL_COMMAND'])) {
            self::fatalError('Need SSH_ORIGINAL_COMMAND in environment.');
        }
        $cmd = $env['SSH_ORIGINAL_COMMAND'];
        chdir(Pluf::f('idf_plugin_syncgit_git_home_dir', '/home/git'));
        $serve = new IDF_Plugin_SyncGit_Serve();
        try {
            $new_cmd = $serve->serve($username, $cmd);
        } catch (Exception $e) {
            self::fatalError($e->getMessage());
        }
        print $new_cmd;
        exit(0);
    }

    /**
     * Control the access rights to the repository.
     *
     * @param string Username
     * @param string Path including the possible .git
     * @param string Type of access. 'readonly' or ('writable')
     * @return mixed False or array(base_git_reps, relative path to repo)
     */
    public function haveAccess($username, $path, $mode='writable')
    {
        if ('.git' == substr($path, -4)) {
            $path = substr($path, 0, -4);
        }
        $sql = new Pluf_SQL('shortname=%s', array($path));
        $projects = Pluf::factory('IDF_Project')->getList(array('filter'=>$sql->gen()));
        if ($projects->count() != 1) {
            return false;
        }
        $project = $projects[0];
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $scm = $conf->getVal('scm', 'git');
        if ($scm != 'git') {
            return false;
        }
        $sql = new Pluf_SQL('login=%s', array($username));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        if ($users->count() != 1 or !$users[0]->active) {
            return false;
        }
        $user = $users[0];
        $request = new StdClass();
        $request->user = $user;
        $request->conf = $conf;
        $request->project = $project;
        if (true === IDF_Precondition::accessTabGeneric($request, 'source_access_rights')) {
            if ($mode == 'readonly') {
                return array(Pluf::f('idf_plugin_syncgit_base_repositories', '/home/git/repositories'),
                             $project->shortname);
            }
            if (true === IDF_Precondition::projectMemberOrOwner($request)) {
                return array(Pluf::f('idf_plugin_syncgit_base_repositories', '/home/git/repositories'),
                             $project->shortname);
            }
        }
        return false;
    }

    /**
     * Die on a message on stderr.
     *
     * @param string Message
     */
    public static function fatalError($mess)
    {
        fwrite(STDERR, $mess."\n");
        exit(1);
    }

    /**
     * Init a new empty bare repository.
     *
     * @param string Full path to the repository
     */
    public function initRepository($fullpath)
    {
        mkdir($fullpath, 0750, true);
        exec(sprintf('git --git-dir=%s init', escapeshellarg($fullpath)), 
             $out, $res);
        if ($res != 0) {
            throw new Exception(sprintf('Init repository error, exit status %d.', $res));
        }
    }

    /**
     * Set the git export value.
     *
     * @param string Relative path of the repository (not .git)
     * @param string Full path of the repository with .git
     */
    public function setGitExport($relpath, $fullpath)
    {
        $sql = new Pluf_SQL('shortname=%s', array($relpath));
        $projects = Pluf::factory('IDF_Project')->getList(array('filter'=>$sql->gen()));
        if ($projects->count() != 1) {
            return $this->gitExportDeny($fullpath);
        }
        $project = $projects[0];
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $scm = $conf->getVal('scm', 'git');
        if ($scm != 'git' or $project->private) {
            return $this->gitExportDeny($fullpath);
        }
        if ('all' == $conf->getVal('source_access_rights', 'all')) {
            return $this->gitExportAllow($fullpath);
        }
        return $this->gitExportDeny($fullpath);
    }

    /**
     * Remove the export flag.
     *
     * @param string Full path to the repository
     */
    public function gitExportDeny($fullpath)
    {
        if (!file_exists($fullpath)) {
            return; // Not created yet.
        }
        @unlink($fullpath.DIRECTORY_SEPARATOR.'git-daemon-export-ok');
        if (file_exists($fullpath.DIRECTORY_SEPARATOR.'git-daemon-export-ok')) {
            throw new Exception('Cannot remove git-daemon-export-ok file.');
        }
        return true;
    }

    /**
     * Set the export flag.
     *
     * @param string Full path to the repository
     */
    public function gitExportAllow($fullpath)
    {
        if (!file_exists($fullpath)) {
            return; // Not created yet.
        }
        touch($fullpath.DIRECTORY_SEPARATOR.'git-daemon-export-ok');
        if (!file_exists($fullpath.DIRECTORY_SEPARATOR.'git-daemon-export-ok')) {
            throw new Exception('Cannot create git-daemon-export-ok file.');
        }
        return true;
    }
}
