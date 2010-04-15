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
 * between indefero and mercurial web-published repositories.
 */
class IDF_Plugin_SyncMercurial
{
    
    /**
     * Entry point of the plugin.
     */
    static public function entry($signal, &$params)
    {
        // First check for the 3 mandatory config variables.
        if (!Pluf::f('idf_plugin_syncmercurial_passwd_file', false) or
            !Pluf::f('idf_plugin_syncmercurial_path', false) or
            !Pluf::f('idf_plugin_syncmercurial_hgrc', false)) {
            return;
        }
        include_once 'File/Passwd/Authdigest.php';
        $plug = new IDF_Plugin_SyncMercurial();
        switch ($signal) {
        case 'IDF_Project::created':
            $plug->processMercurialCreate($params['project']);
            break;
        case 'IDF_Project::membershipsUpdated':
            $plug->processSyncAuthz($params['project']);
            break;
        case 'Pluf_User::passwordUpdated':
            $plug->processSyncPasswd($params['user']);
            break;
        case 'hgchangegroup.php::run':
            $plug->processSyncTimeline($params);
            break;
        }
    }

    /**
     * Run hg init command to create the corresponding Mercurial
     * repository.
     *
     * @param IDF_Project 
     * @return bool Success
     */
    function processMercurialCreate($project)
    {
        if ($project->getConf()->getVal('scm') != 'mercurial') {
            return false;
        }
        $shortname = $project->shortname;
        if (false===($mercurial_path=Pluf::f('idf_plugin_syncmercurial_path',false))) {
            throw new Pluf_Exception_SettingError("'idf_plugin_syncmercurial_path' must be defined in your configuration file.");
        }

        if (file_exists($mercurial_path.'/'.$shortname)) {
            throw new Exception(sprintf(__('The repository %s already exists.'),
                                        $mercurial_path.'/'.$shortname));
        }
        $return = 0;
        $output = array();
        $cmd = sprintf(Pluf::f('hg_path', 'hg').' init %s', 
                       escapeshellarg($mercurial_path.'/'.$shortname));
        $cmd = Pluf::f('idf_exec_cmd_prefix', '').$cmd;
        $ll = exec($cmd, $output, $return);
        return ($return == 0);
    }
    
    /**
     * Synchronise an user's password.
     *
     * @param Pluf_User
     */
    function processSyncPasswd($user)
    {
        $passwd_file = Pluf::f('idf_plugin_syncmercurial_passwd_file');
        if (!file_exists($passwd_file) or !is_writable($passwd_file)) {
            return false;
        }
        $ht = new File_Passwd_Authbasic($passwd_file);
        $ht->load();
        $ht->setMode(Pluf::f('idf_plugin_syncmercurial_passwd_mode',
                             FILE_PASSWD_SHA)); 
        if ($ht->userExists($user->login)) {
            $ht->changePasswd($user->login, $this->getMercurialPass($user));
        } else {
            $ht->addUser($user->login, $this->getMercurialPass($user));
        }
        $ht->save();
        return true;
    }

    /**
     * Synchronize the hgrc file and the passwd file for the project.
     *
     * @param IDF_Project
     */
    function processSyncAuthz($project)
    {
        if ($project->getConf()->getVal('scm') != 'mercurial') {
            return false;
        }
        $this->SyncAccess($project);
        $this->generateProjectPasswd($project);
    }

    /**
     * Get the repository password for the user
     */
    function getMercurialPass($user){
        return substr(sha1($user->password.Pluf::f('secret_key')), 0, 8);
    }

    /**
     * For a particular project: update all passwd information
     */
    function generateProjectPasswd($project)
    {
        $passwd_file = Pluf::f('idf_plugin_syncmercurial_passwd_file');
        if (!file_exists($passwd_file) or !is_writable($passwd_file)) {
            throw new Exception (sprintf(__('%s does not exist or is not writable.'), $passwd_file));
        }
        $ht = new File_Passwd_Authbasic($passwd_file);
        $ht->setMode(Pluf::f('idf_plugin_syncmercurial_passwd_mode',
                             FILE_PASSWD_SHA)); 
        $ht->load();
        $mem = $project->getMembershipData();
        $members = array_merge((array)$mem['members'], (array)$mem['owners'], 
                               (array)$mem['authorized']);
        foreach($members as $user) {
            if ($ht->userExists($user->login)) {
                $ht->changePasswd($user->login, $this->getMercurialPass($user));
            } else {
                $ht->addUser($user->login, $this->getMercurialPass($user));
            }
        }
        $ht->save();
    }

    /**
     * Generate the hgrc file
     */
    function SyncAccess($project)
    {
        $shortname = $project->shortname;
        $hgrc_file = Pluf::f('idf_plugin_syncmercurial_path').sprintf('/%s/.hg/hgrc', $shortname);

        // Get allow_push list
        $allow_push = '';
        $mem = $project->getMembershipData();
        foreach ($mem['owners'] as $v) {
                $allow_push .= $v->login.' ';
        }
        foreach ($mem['members'] as $v) {
                $allow_push .= $v->login.' ';
        }

        // Generate hgrc content 
        if (is_file($hgrc_file)) {
            $tmp_content = parse_ini_file($hgrc_file, true);
            $tmp_content['web']['allow_push'] = $allow_push;
        }
        else {
            $tmp_content = Pluf::f('idf_plugin_syncmercurial_hgrc');
            $tmp_content['web']['allow_push'] = $allow_push;
        }
        $fcontent = '';
        foreach ($tmp_content as $key => $elem){
            $fcontent .= '['.$key."]\n";
            foreach ($elem as $key2 => $elem2){
                $fcontent .= $key2.' = '.$elem2."\n"; 
            }
        }
        file_put_contents($hgrc_file, $fcontent, LOCK_EX);
        
        // Generate private repository config file
        $private_file = Pluf::f('idf_plugin_syncmercurial_private_include');
        $notify_file = Pluf::f('idf_plugin_syncmercurial_private_notify');
        $fcontent = '';
        foreach (Pluf::factory('IDF_Project')->getList() as $project) {
            $conf = new IDF_Conf();
            $conf->setProject($project);
            if ($project->private == true){
                $mem = $project->getMembershipData();
                $user = '';
                foreach ($mem['owners'] as $v) {
                    $user .= $v->login.' ';
                }
                foreach ($mem['members'] as $v) {
                    $user .= $v->login.' ';
                }
                foreach ($mem['authorized'] as $v) {
                    $user .= $v->login.' ';
                }
                $fcontent .= '<Location '. sprintf(Pluf::f('idf_plugin_syncmercurial_private_url'), $project->shortname).'>'."\n";
                $fcontent .= 'AuthType Basic'."\n";
                $fcontent .= 'AuthName "Restricted"'."\n";
                $fcontent .= sprintf('AuthUserFile %s', Pluf::f('idf_plugin_syncmercurial_passwd_file'))."\n";
                $fcontent .= sprintf('Require user %s', $user)."\n";
                $fcontent .= '</Location>'."\n\n";
            }
        }
        file_put_contents($private_file, $fcontent, LOCK_EX);
        file_put_contents($notify_file, ' ', LOCK_EX);
        return true;
    }

    /**
     * Update the timeline in post commit.
     *
     */
    public function processSyncTimeline($params)
    {
        $pname = basename($params['rel_dir']);
        try {
            $project = IDF_Project::getOr404($pname);
        } catch (Pluf_HTTP_Error404 $e) {
            Pluf_Log::event(array('IDF_Plugin_SyncMercurial::processSyncTimeline', 'Project not found.', array($pname, $params)));
            return false; // Project not found
        }
        // Now we have the project and can update the timeline
        Pluf_Log::debug(array('IDF_Plugin_SyncMercurial::processSyncTimeline', 'Project found', $pname, $project->id));
        IDF_Scm::syncTimeline($project, true);
        Pluf_Log::event(array('IDF_Plugin_SyncMercurial::processSyncTimeline', 'sync', array($pname, $project->id)));
        

    }
}
