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
 * This class implements the cache storage for the Git commits.
 *
 * The storage is simple. Each commit is linked to a project to drop
 * the cache when the project is dropped. The key is the commit hash
 * and the data is the date, author and one line title of information.
 *
 * A clean interface is available to bulk set/get a series of commit
 * info with the minimum of SQL queries. The goal is to be fast.
 */
class IDF_Scm_Cache_Git extends Pluf_Model
{
    public $_model = __CLASS__;

    /**
     * The current project to limit the search to.
     */
    public $_project = null;

    /**
     * Store in the cache blob infos.
     *
     * The info is an array of stdClasses, with hash, date, title and
     * author properties.
     *
     * @param array Blob infos
     */
    public function store($infos)
    {
        foreach ($infos as $blob) {
            $cache = new IDF_Scm_Cache_Git();
            $cache->project = $this->_project;
            $cache->githash = $blob->hash;
            $blob->title = IDF_Commit::toUTF8($blob->title);
            $cache->content = $blob->date.chr(31).$blob->author.chr(31).$blob->title;
            $sql = new Pluf_SQL('project=%s AND githash=%s',
                                array($this->_project->id, $blob->hash));
            if (0 == Pluf::factory(__CLASS__)->getCount(array('filter' => $sql->gen()))) {
                $cache->create();
            }
        }
    }

    /**
     * Get for the given hashes the corresponding date, title and
     * author.
     *
     * It returns an hash indexed array with the info. If an hash is
     * not in the db, the key is not set.
     *
     * Note that the hashes must always come from internal tools.
     *
     * @param array Hashes to get info
     * @return array Blob infos
     */
    public function retrieve($hashes)
    {
        $res = array();
        $db = $this->getDbConnection();
        $hashes = array_map(array($db, 'esc'), $hashes);
        $sql = new Pluf_SQL('project=%s AND githash IN ('.implode(', ', $hashes).')', 
                            array($this->_project->id));
        foreach (Pluf::factory(__CLASS__)->getList(array('filter' => $sql->gen())) as $blob) {
            $tmp = explode(chr(31), $blob->content, 3);

            $res[$blob->githash] = (object) array(
                                                  'hash' => $blob->githash,
                                                  'date' => $tmp[0],
                                                  'title' => $tmp[2],
                                                  'author' => $tmp[1],
                                                  );
        }
        return $res;
    }

    /**
     * The storage is composed of 4 columns, id, project, hash and the
     * raw data.
     */
    function init()
    {
        $this->_a['table'] = 'idf_scm_cache_git';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true, 
                                  ),
                            'project' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Project',
                                  'blank' => false,
                                  ),
                            'githash' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 40,
                                  'index' => true,
                                  ),
                            'content' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => false,
                                  ),
                            );
    }
}