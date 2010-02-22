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
            return;
        }
        @touch(Pluf::f('idf_plugin_syncgit_sync_file'));
        @chmod(Pluf::f('idf_plugin_syncgit_sync_file'), 0777);
    }
}
