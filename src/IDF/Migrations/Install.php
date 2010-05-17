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
 * Setup of a clean InDefero.
 *
 * It creates all the tables for the application.
 */

function IDF_Migrations_Install_setup($params=null)
{
    $models = array(
                    'IDF_Project',
                    'IDF_Tag',
                    'IDF_Issue',
                    'IDF_IssueComment',
                    'IDF_Conf',
                    'IDF_Upload',
                    'IDF_Search_Occ',
                    'IDF_IssueFile',
                    'IDF_Commit',
                    'IDF_Timeline',
                    'IDF_WikiPage',
                    'IDF_WikiRevision',
                    'IDF_Review',
                    'IDF_Review_Patch',
                    'IDF_Review_Comment',
                    'IDF_Review_FileComment',
                    'IDF_Key',
                    'IDF_Scm_Cache_Git',
                    'IDF_Queue',
                    'IDF_Gconf',
                    );
    $db = Pluf::db();
    $schema = new Pluf_DB_Schema($db);
    foreach ($models as $model) {
        $schema->model = new $model();
        $schema->createTables();
    }
    // Install the permissions
    $perm = new Pluf_Permission();
    $perm->name = 'Project membership';
    $perm->code_name = 'project-member';
    $perm->description = 'Permission given to project members.';
    $perm->application = 'IDF';
    $perm->create();
    $perm = new Pluf_Permission();
    $perm->name = 'Project ownership';
    $perm->code_name = 'project-owner';
    $perm->description = 'Permission given to project owners.';
    $perm->application = 'IDF';
    $perm->create();
    $perm = new Pluf_Permission();
    $perm->name = 'Project authorized users';
    $perm->code_name = 'project-authorized-user';
    $perm->description = 'Permission given to users allowed to access a project.';
    $perm->application = 'IDF';
    $perm->create();
}

function IDF_Migrations_Install_teardown($params=null)
{
    $perm = Pluf_Permission::getFromString('IDF.project-member');
    if ($perm) $perm->delete();
    $perm = Pluf_Permission::getFromString('IDF.project-owner');
    if ($perm) $perm->delete();
    $perm = Pluf_Permission::getFromString('IDF.project-authorized-user');
    if ($perm) $perm->delete();
    $models = array(
                    'IDF_Gconf',
                    'IDF_Queue',
                    'IDF_Scm_Cache_Git',
                    'IDF_Key',
                    'IDF_Review_FileComment',
                    'IDF_Review_Comment',
                    'IDF_Review_Patch',
                    'IDF_Review',
                    'IDF_WikiRevision',
                    'IDF_WikiPage',
                    'IDF_Timeline',
                    'IDF_IssueFile',
                    'IDF_Search_Occ',
                    'IDF_Upload',
                    'IDF_Conf',
                    'IDF_IssueComment',
                    'IDF_Issue',
                    'IDF_Tag',
                    'IDF_Commit',
                    'IDF_Project',
                    );
    $db = Pluf::db();
    $schema = new Pluf_DB_Schema($db);
    foreach ($models as $model) {
        $schema->model = new $model();
        $schema->dropTables();
    }
}