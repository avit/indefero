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
 * Remove the old review and add the new one.
 *
 * This is a destructive operation.
 */

function IDF_Migrations_13NewReview_up($params=null)
{
    $extra = (Pluf::f('db_engine') == 'PostgreSQL') ? ' CASCADE' : '';
    $pfx = Pluf::f('db_table_prefix');
    $tables = array('idf_review_filecomments',
                    'idf_review_patches',
                    'idf_review_pluf_user_assoc',
                    'idf_review_idf_tag_assoc',
                    'idf_reviews');
    $db = Pluf::db();
    foreach ($tables as $table) {
        $db->execute('DROP TABLE IF EXISTS '.$pfx.$table.$extra);
    }
    $models = array(
                    'IDF_Review',
                    'IDF_Review_Patch',
                    'IDF_Review_Comment',
                    'IDF_Review_FileComment',
                    );
    $db = Pluf::db();
    $schema = new Pluf_DB_Schema($db);
    foreach ($models as $model) {
        $schema->model = new $model();
        $schema->createTables();
    }
}

function IDF_Migrations_13NewReview_down($params=null)
{
    // We do nothing as we cannot go back to the old reviews
}