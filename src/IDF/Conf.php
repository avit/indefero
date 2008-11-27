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
 * Configuration of a project.
 *
 * It is just storing a list of key/value
 * pairs. We can that way store quite a lot of data.
 */
class IDF_Conf extends Pluf_Model
{
    public $_model = __CLASS__;
    public $datacache = null;
    public $f = null;
    protected $_project = null;

    function init()
    {
        $this->_a['table'] = 'idf_conf';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  //It is automatically added.
                                  'blank' => true, 
                                  ),
                            'project' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Project',
                                  'blank' => false,
                                  'verbose' => __('project'),
                                  ),
                            'vkey' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 50,
                                  'verbose' => __('key'),
                                  ),
                            'vdesc' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => false,
                                  'verbose' => __('value'),
                                  ),
                            );
        $this->_a['idx'] = array('project_vkey_idx' =>
                                 array(
                                       'col' => 'project, vkey',
                                       'type' => 'unique',
                                       ),
                                 );
        $this->f = new IDF_Config_DataProxy($this);
    }

    function setProject($project)
    {
        $this->datacache = null;
        $this->_project = $project;
    }

    function initCache()
    {
        $this->datacache = new ArrayObject();
        $sql = new Pluf_SQL('project=%s', $this->_project->id);
        foreach ($this->getList(array('filter' => $sql->gen())) as $val) {
            $this->datacache[$val->vkey] = $val->vdesc;
        }
    }

    /**
     * FIXME: This is not efficient when setting a large number of
     * values in a loop.
     */
    function setVal($key, $value)
    {
        if (!is_null($this->getVal($key, null)) 
            and $value == $this->getVal($key)) {
            return;
        }
        $this->delVal($key, false);
        $conf = new IDF_Conf();
        $conf->project = $this->_project;
        $conf->vkey = $key;
        $conf->vdesc = $value;
        $conf->create();
        $this->initCache();
    }

    function getVal($key, $default='')
    {
        if ($this->datacache === null) {
            $this->initCache();
        }
        return (isset($this->datacache[$key])) ? $this->datacache[$key] : $default;
    }

    function delVal($key, $initcache=true)
    {
        $gconf = new IDF_Conf();
        $sql = new Pluf_SQL('vkey=%s AND project=%s', array($key, $this->_project->id));
        foreach ($gconf->getList(array('filter' => $sql->gen())) as $c) {
            $c->delete();
        }
        if ($initcache) {
            $this->initCache();
        }
    }
}
