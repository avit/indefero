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
            if (!file_exists($mpath)) {
                mkdir($mpath, 0750, true);
            }
            $this->initRepository($fullpath);
            $this->setGitExport($relpath, $fullpath);
        }
        $new_cmd = sprintf("%s '%s'", $verb, $fullpath);
        Pluf_Log::info(array('IDF_Plugin_Git_Serve::serve', $username, $cmd, $new_cmd));
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
        $home = (Pluf::f('idf_plugin_syncgit_git_home_dir', '/home/git'));
        if (!is_dir($home) || is_link($home)) {
          throw new Pluf_Exception_Setting_error(sprintf(
              '%s does not exist! Did you set up your git user? '.
              'Set "idf_plugin_syncgit_git_home_dir". to git\'s $HOME.',
              $home)
          );
        }
        chdir($home);
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
        if (true === IDF_Precondition::accessSource($request)) {
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
        if (!file_exists($fullpath)) {
            mkdir($fullpath, 0750, true);
        }
        $out = array();
        $res = 0;
        exec(sprintf(Pluf::f('idf_exec_cmd_prefix', '').
                     Pluf::f('git_path', 'git').' --git-dir=%s init', escapeshellarg($fullpath)), 
             $out, $res);
        if ($res != 0) {
            Pluf_Log::error(array('IDF_Plugin_Git_Serve::initRepository', $res, $fullpath));
            throw new Exception(sprintf('Init repository error, exit status %d.', $res));
        }
        Pluf_Log::event(array('IDF_Plugin_Git_Serve::initRepository', 'success', $fullpath));
        // Add the post-update hook by removing the original one and add the 
        // Indefero's one.
        $p = realpath(dirname(__FILE__).'/../../../../scripts/git-post-update');
        $p = Pluf::f('idf_plugin_syncgit_post_update', $p);
        if (!@unlink($fullpath.'/hooks/post-update')) {
            Pluf_Log::warn(array('IDF_Plugin_Git_Serve::initRepository', 
                                 'post-update hook removal error.', 
                                 $fullpath.'/hooks/post-update'));
            return;
        }
        $out = array();
        $res = 0;
        exec(sprintf(Pluf::f('idf_exec_cmd_prefix', '').'ln -s %s %s', 
                     escapeshellarg($p), 
                     escapeshellarg($fullpath.'/hooks/post-update')),
             $out, $res);
        if ($res != 0) {
            Pluf_Log::warn(array('IDF_Plugin_Git_Serve::initRepository', 
                                 'post-update hook creation error.', 
                                 $fullpath.'/hooks/post-update'));
            return;
        }
        Pluf_Log::debug(array('IDF_Plugin_Git_Serve::initRepository', 
                              'Added post-update hook.', $fullpath));
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
        if ($projects->count() != 1 and file_exists($fullpath)) {
            return $this->gitExportDeny($fullpath);
        }
        $project = $projects[0];
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $scm = $conf->getVal('scm', 'git');
        if ($scm == 'git' and !file_exists($fullpath)) {
            // No repository yet, just skip
            return false;
        }
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
