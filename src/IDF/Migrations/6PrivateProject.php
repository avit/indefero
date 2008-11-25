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
 * Add the private column for the project.
 */

function IDF_Migrations_6PrivateProject_up($params=null)
{
    $table = Pluf::factory('IDF_Project')->getSqlTable();
    $sql = array();
    $sql['PostgreSQL'] = 'ALTER TABLE '.$table.' ADD COLUMN "private" INTEGER DEFAULT 0';
    $sql['MySQL'] = 'ALTER TABLE '.$table.' ADD COLUMN `private` INTEGER DEFAULT 0';
    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }
    $db->execute($sql[$engine]);
    $perm = new Pluf_Permission();
    $perm->name = 'Project authorized users';
    $perm->code_name = 'project-authorized-user';
    $perm->description = 'Permission given to users allowed to access a project.';
    $perm->application = 'IDF';
    $perm->create();
}

function IDF_Migrations_6PrivateProject_down($params=null)
{
    $perm = Pluf_Permission::getFromString('IDF.project-authorized-user');
    if ($perm) $perm->delete();
    $table = Pluf::factory('IDF_Project')->getSqlTable();
    $sql = array();
    $sql['PostgreSQL'] = 'ALTER TABLE '.$table.' DROP COLUMN "private"';
    $sql['MySQL'] = 'ALTER TABLE '.$table.' DROP COLUMN `private`';
    $db = Pluf::db();
    $engine = Pluf::f('db_engine');
    if (!isset($sql[$engine])) {
        throw new Exception('SQLite complex migration not supported.');
    }
    $db->execute($sql[$engine]);

}