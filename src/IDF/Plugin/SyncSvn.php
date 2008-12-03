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

require_once 'File/Passwd/Authdigest.php'; // $ pear install File_Passwd

/**
 * This classes is a plugin which allows to synchronise access rights between indefero 
 * and a DAV powered SVN repository.
 */
class IDF_Plugin_SyncSvn
{
    
    /**
     * Entry point of the each plugins. 
     */
    static function entry($signal, $params){
        // if not actif, do nothing

        if ($signal == 'IDF_Project::created'){
            $project = $params['project'];

            $plug = new IDF_Plugin_SyncSVN();
            //$plug->processSVNCreate($project->shortname);

        }else if ($signal == 'IDF_Project::membershipsUpdated'){
            $project = $params['project'];
            
            $plug = new IDF_Plugin_SyncSVN();
            $plug->processSyncAuthz($project);

        }else if ($signal == 'IDF_User::passwordUpdated'){
            $plug = new IDF_Plugin_SyncSVN();
            $plug->processSyncPasswd($params['user']);
        }else {
            // do nothing
        }
    }

    /**
     * Run svnadmin command to create a usable SVN repository
     * @param Project name
     */
    function processSVNCreate($shortname){

        $svn_path = Pluf::f('idf_plugin_syncsvn_svn_path');
        $svn_import_path = Pluf::f('idf_plugin_syncsvn_svn_import_path');
        $chown_user = Pluf::f('idf_plugin_syncsvn_svn_import_path');

        $c = 0;
        $createsvn = "svnadmin create ".$svn_path."/".$shortname;
        Pluf_Utils::runExternal($createsvn, $c);

        if ($svn_import_path != ""){
            //perform initial import
            // TODO
        }

        if ($chown_user != ""){
            $chown = "chown ".$chown_user." ".$svn_path."/".$shortname." -R";
            Pluf_Utils::runExternal($chown, $c);
        }
    }
    
    /**
     * Synchronise an user's password
     * @param $user Pluf_User
     */
    function processSyncPasswd($user){
        $passwd_file = Pluf::f('idf_plugin_syncsvn_passwd_file');
        $ht = new File_Passwd_Authbasic($passwd_file);
        $ht->parse();
        $ht->setMode(FILE_PASSWD_SHA); // not anymore a option
        $ht->addUser($user, $this->getSVNPass($user));
        $ht->save();
    }

    /**
     * Synchronize the authz file and the passwd file for the project
     * @param $project IDF_Project
     */
    function processSyncAuthz($project){
        //synchronise authz file        
        $this->SyncAccess();
        //synchronise pass file for 
        $this->generateProjectPasswd($project);
    }

    /**
     * Get the repository password for the user
     */
    function getSVNPass($user){
        return substr(sha1($user->password.Pluf::f('secret_key')), 0, 8);
    }

    /**
     * For a particular project: update all passwd information
     */
    function generateProjectPasswd($project){
        $passwd_file = Pluf::f('idf_plugin_syncsvn_passwd_file');
        $ht = new File_Passwd_Authbasic($passwd_file);

        $ht->setMode(FILE_PASSWD_SHA); // not anymore a option
        $ht->parse();

        $mem = $project->getMembershipData();
        $members = $mem['members'];
        $owners = $mem['owners'];

        foreach($owners as $v){
            $ht->addUser($v->login, $this->getSVNPass($v));            
        }

        foreach($members as $v){
            $ht->addUser($v->login, $this->getSVNPass($v));        
        }
        $ht->save();
    }

    /**
     * Generate the dav_svn.authz file
     */
    function SyncAccess(){
        $authz_file = Pluf::f('idf_plugin_syncsvn_authz_file');
        $access_owners = Pluf::f('idf_plugin_syncsvn_access_owners');
        $access_members = Pluf::f('idf_plugin_syncsvn_access_members');
        $access_all = Pluf::f('idf_plugin_syncsvn_access_all');
        $access_all_pivate = Pluf::f('idf_plugin_syncsvn_access_all_pivate');

        $projects = Pluf::factory('IDF_Project')->getList();

        $fcontent = "";
    
        // for each project
        foreach($projects as $project){

            $conf = new IDF_Conf();
            $conf->setProject($project);

            if ($conf->getVal('scm', "") == "svn"){

	            $mem = $project->getMembershipData();
	            $members = $mem['members'];
	            $owners = $mem['owners'];

                // [shortname:/]
                $fcontent .= "[".$project->shortname.":/]\n";    

                // login = rw
	            foreach($owners as $v){
                    	$fcontent .= $v->login." = ".$access_owners."\n";
	            }
                // login = rw
	            foreach($members as $v){
		            $fcontent .= $v->login." = ".$access_members."\n";
	            }

                // access for all users
                if ($project->private == true){
                    $fcontent .= "* = ".$access_all_pivate."\n";
                }else{
                    $fcontent .= "* = ".$access_all."\n";
                }

	            $fcontent .= "\n";
            } //end if SVN
        }

        file_put_contents($authz_file, $fcontent, LOCK_EX);

        return 0;
    }
}

?>
