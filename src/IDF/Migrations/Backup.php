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
 * Backup of InDefero.
 *
 * !! You need also to backup Pluf if you want the full backup. !!
 *
 * @param string Path to the folder where to store the backup
 * @param string Name of the backup (null)
 * @return int The backup was correctly written
 */
function IDF_Migrations_Backup_run($folder, $name=null)
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
    // Now, for each table, we dump the content in json, this is a
    // memory intensive operation
    $to_json = array();
    foreach ($models as $model) {
        $to_json[$model] = Pluf_Test_Fixture::dump($model, false);
    }
    if (null == $name) {
        $name = date('Y-m-d');
    }
    return file_put_contents(sprintf('%s/%s-IDF.json', $folder, $name),
                             json_encode($to_json), LOCK_EX);
}

/**
 * Restore IDF from a backup.
 *
 * @param string Path to the backup folder
 * @param string Backup name
 * @return bool Success
 */
function IDF_Migrations_Backup_restore($folder, $name)
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
    $full_data = json_decode(file_get_contents(sprintf('%s/%s-IDF.json', $folder, $name)), true);
    foreach ($full_data as $model => $data) {
        Pluf_Test_Fixture::load($data, false);
    }
    return true;
}