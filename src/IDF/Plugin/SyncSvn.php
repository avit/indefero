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
 * This classes is a plugin which allows to synchronise access rights
 * between indefero and a DAV powered Subversion repository.
 */
class IDF_Plugin_SyncSvn
{
    
    /**
     * Entry point of the plugin.
     */
    static public function entry($signal, &$params)
    {
        // First check for the 3 mandatory config variables.
        if (!Pluf::f('idf_plugin_syncsvn_authz_file', false) or
            !Pluf::f('idf_plugin_syncsvn_passwd_file', false) or
            !Pluf::f('idf_plugin_syncsvn_svn_path', false)) {
            return;
        }
        include_once 'File/Passwd/Authdigest.php'; // $ pear install File_Passwd
        $plug = new IDF_Plugin_SyncSvn();
        switch ($signal) {
        case 'IDF_Project::created':
            $plug->processSvnCreate($params['project']);
            break;
        case 'IDF_Project::membershipsUpdated':
            $plug->processSyncAuthz($params['project']);
            break;
        case 'Pluf_User::passwordUpdated':
            $plug->processSyncPasswd($params['user']);
            break;
        case 'IDF_Project::preDelete':
            $plug->processSvnDelete($params['project']);
            break;
        case 'svnpostcommit.php::run':
            $plug->processSvnUpdateTimeline($params);
            break;
        }
    }

    /**
     * Run svnadmin command to create the corresponding Subversion
     * repository.
     *
     * @param IDF_Project 
     * @return bool Success
     */
    function processSvnCreate($project)
    {
        if ($project->getConf()->getVal('scm') != 'svn') {
            return false;
        }
        $shortname = $project->shortname;
        if (false===($svn_path=Pluf::f('idf_plugin_syncsvn_svn_path',false))) {
            throw new Pluf_Exception_SettingError("'idf_plugin_syncsvn_svn_path' must be defined in your configuration file.");
        }
        if (file_exists($svn_path.'/'.$shortname)) {
            throw new Exception(sprintf(__('The repository %s already exists.'),
                                        $svn_path.'/'.$shortname));
        }
        $return = 0;
        $output = array();
        $cmd = sprintf(Pluf::f('svnadmin_path', 'svnadmin').' create %s', 
                       escapeshellarg($svn_path.'/'.$shortname));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        $ll = exec($cmd, $output, $return);
        if ($return != 0) {
            Pluf_Log::error(array('IDF_Plugin_SyncSvn::processSvnCreate', 
                                  'Error', 
                                  array('path' => $svn_path.'/'.$shortname,
                                        'output' => $output)));
            return;
        }
        $p = realpath(dirname(__FILE__).'/../../../scripts/svn-post-commit');
        exec(sprintf(Pluf::f('idf_exec_cmd_prefix', '').'ln -s %s %s', 
                     escapeshellarg($p), 
                     escapeshellarg($svn_path.'/'.$shortname.'/hooks/post-commit')),
             $out, $res);
        if ($res != 0) {
            Pluf_Log::warn(array('IDF_Plugin_SyncSvn::processSvnCreate', 
                                 'post-commit hook creation error.', 
                                 $svn_path.'/'.$shortname.'/hooks/post-commit'));
            return;
        }
        $p = realpath(dirname(__FILE__).'/../../../scripts/svn-post-revprop-change');
        exec(sprintf(Pluf::f('idf_exec_cmd_prefix', '').'ln -s %s %s', 
                     escapeshellarg($p), 
                     escapeshellarg($svn_path.'/'.$shortname.'/hooks/post-revprop-change')),
             $out, $res);
        if ($res != 0) {
            Pluf_Log::warn(array('IDF_Plugin_SyncSvn::processSvnCreate', 
                                 'post-revprop-change hook creation error.', 
                                 $svn_path.'/'.$shortname.'/hooks/post-revprop-change'));
            return;
        }

        return ($return == 0);
    }

    /**
     * Remove the project from the drive and update the access rights.
     *
     * @param IDF_Project 
     * @return bool Success
     */
    function processSvnDelete($project)
    {
        if (!Pluf::f('idf_plugin_syncsvn_remove_orphans', false)) {
            return;
        }
        if ($project->getConf()->getVal('scm') != 'svn') {
            return false;
        }
        $this->SyncAccess($project); // exclude $project
        $shortname = $project->shortname;
        if (false===($svn_path=Pluf::f('idf_plugin_syncsvn_svn_path',false))) {
            throw new Pluf_Exception_SettingError("'idf_plugin_syncsvn_svn_path' must be defined in your configuration file.");
        }
        if (file_exists($svn_path.'/'.$shortname)) {
            $cmd = Pluf::f('idf_exec_cmd_prefix', '').'rm -rf '.$svn_path.'/'.$shortname;
            exec($cmd);
        }
    }
    
    /**
     * Synchronise an user's password.
     *
     * @param Pluf_User
     */
    function processSyncPasswd($user)
    {
        $passwd_file = Pluf::f('idf_plugin_syncsvn_passwd_file');
        if (!file_exists($passwd_file) or !is_writable($passwd_file)) {
            return false;
        }
        $ht = new File_Passwd_Authbasic($passwd_file);
        $ht->load();
        $ht->setMode(FILE_PASSWD_SHA); 
        if ($ht->userExists($user->login)) {
            $ht->changePasswd($user->login, $this->getSvnPass($user));
        } else {
            $ht->addUser($user->login, $this->getSvnPass($user));
        }
        $ht->save();
        return true;
    }

    /**
     * Synchronize the authz file and the passwd file for the project.
     *
     * @param IDF_Project
     */
    function processSyncAuthz($project)
    {
        $this->SyncAccess();
        $this->generateProjectPasswd($project);
    }

    /**
     * Get the repository password for the user
     */
    function getSvnPass($user){
        return substr(sha1($user->password.Pluf::f('secret_key')), 0, 8);
    }

    /**
     * For a particular project: update all passwd information
     */
    function generateProjectPasswd($project)
    {
        $passwd_file = Pluf::f('idf_plugin_syncsvn_passwd_file');
        if (!file_exists($passwd_file) or !is_writable($passwd_file)) {
            return false;
        }
        $ht = new File_Passwd_Authbasic($passwd_file);
        $ht->setMode(FILE_PASSWD_SHA); 
        $ht->load();
        $mem = $project->getMembershipData();
        $members = array_merge((array)$mem['members'], (array)$mem['owners'], 
                               (array)$mem['authorized']);
        foreach($members as $user) {
            if ($ht->userExists($user->login)) {
                $ht->changePasswd($user->login, $this->getSvnPass($user));
            } else {
                $ht->addUser($user->login, $this->getSvnPass($user));
            }
        }
        $ht->save();
    }

    /**
     * Generate the dav_svn.authz file
     *
     * We rebuild the complete file each time. This is just to be sure
     * not to bork the rights when trying to just edit part of the
     * file.
     *
     * @param IDF_Project Possibly exclude a project (null)
     */
    function SyncAccess($exclude=null)
    {
        $authz_file = Pluf::f('idf_plugin_syncsvn_authz_file');
        $access_owners = Pluf::f('idf_plugin_syncsvn_access_owners', 'rw');
        $access_members = Pluf::f('idf_plugin_syncsvn_access_members', 'rw');
        $access_extra = Pluf::f('idf_plugin_syncsvn_access_extra', 'r');
        $access_public = Pluf::f('idf_plugin_syncsvn_access_public', 'r');
        $access_public_priv = Pluf::f('idf_plugin_syncsvn_access_private', '');
        if (!file_exists($authz_file) or !is_writable($authz_file)) {
            return false;
        }
        $fcontent = '';
        foreach (Pluf::factory('IDF_Project')->getList() as $project) {
            if ($exclude and $exclude->id == $project->id) {
                continue;
            }
            $conf = new IDF_Conf();
            $conf->setProject($project);
            if ($conf->getVal('scm') != 'svn' or 
                strlen($conf->getVal('svn_remote_url')) > 0) {
                continue;
            }
            $mem = $project->getMembershipData();
            // [shortname:/]
            $fcontent .= '['.$project->shortname.':/]'."\n";    
            foreach ($mem['owners'] as $v) {
                $fcontent .= $v->login.' = '.$access_owners."\n";
            }
            foreach ($mem['members'] as $v) {
                $fcontent .= $v->login.' = '.$access_members."\n";
            }
            // access for all users
            if ($project->private == true) {
                foreach ($mem['authorized'] as $v) {
                    $fcontent .= $v->login.' = '.$access_extra."\n";
                }
                $fcontent .= '* = '.$access_public_priv."\n";
            } else {
                $fcontent .= '* = '.$access_public."\n";
            }
            $fcontent .= "\n";
        }
        file_put_contents($authz_file, $fcontent, LOCK_EX);
        return true;
    }

    /**
     * Update the timeline in post commit.
     *
     */
    public function processSvnUpdateTimeline($params)
    {
        $pname = basename($params['repo_dir']);
        try {
            $project = IDF_Project::getOr404($pname);
        } catch (Pluf_HTTP_Error404 $e) {
            Pluf_Log::event(array('IDF_Plugin_SyncSvn::processSvnUpdateTimeline', 'Project not found.', array($pname, $params)));
            return false; // Project not found
        }
        // Now we have the project and can update the timeline
        Pluf_Log::debug(array('IDF_Plugin_SyncGit::processSvnUpdateTimeline', 'Project found', $pname, $project->id));
        IDF_Scm::syncTimeline($project, true);
        Pluf_Log::event(array('IDF_Plugin_SyncGit::processSvnUpdateTimeline', 'sync', array($pname, $project->id)));
        

    }
}
