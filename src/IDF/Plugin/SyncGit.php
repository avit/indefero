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
 * This class is a plugin which allows to synchronise access riths
 * between InDefero and a common restricted SSH account for git
 * access.
 *
 * As the authentication is directly performed by accessing the
 * InDefero database, we only need to synchronize the SSH keys. This
 * synchronization process can only be performed by a process running
 * under the git user as we need to write in
 * /home/git/.ssh/authorized_keys
 *
 * So, here, we are just creating a file informing that a sync needs
 * to be done. We connect this plugin to the IDF_Key::postSave signal.
 */
class IDF_Plugin_SyncGit
{
    /**
     * Entry point of the plugin.
     */
    static public function entry($signal, &$params)
    {
        // First check for the single mandatory config variable.
        if (!Pluf::f('idf_plugin_syncgit_sync_file', false)) {
            Pluf_Log::debug('IDF_Plugin_SyncGit plugin not configured.');
            return;
        }
        if ($signal != 'gitpostupdate.php::run') {
            Pluf_Log::event('IDF_Plugin_SyncGit', 'create', 
                            Pluf::f('idf_plugin_syncgit_sync_file'));
            @touch(Pluf::f('idf_plugin_syncgit_sync_file'));
            @chmod(Pluf::f('idf_plugin_syncgit_sync_file'), 0777);
        } else {
            self::postUpdate($signal, $params);
        }
    }

    /**
     * Entry point for the post-update signal.
     *
     * It tries to find the name of the project, when found it runs an
     * update of the timeline.
     */
    static public function postUpdate($signal, &$params)
    {
        // Chop the ".git" and get what is left
        $pname = basename($params['git_dir'], '.git');
        try {
            $project = IDF_Project::getOr404($pname);
        } catch (Pluf_HTTP_Error404 $e) {
            Pluf_Log::event(array('IDF_Plugin_SyncGit::postUpdate', 'Project not found.', array($pname, $params)));
            return false; // Project not found
        }
        // Now we have the project and can update the timeline
        Pluf_Log::debug(array('IDF_Plugin_SyncGit::postUpdate', 'Project found', $pname, $project->id));
        IDF_Scm::syncTimeline($project, true);
        Pluf_Log::event(array('IDF_Plugin_SyncGit::postUpdate', 'sync', array($pname, $project->id)));
    }
}
