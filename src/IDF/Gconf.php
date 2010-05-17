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
 * Configuration of the objects.
 *
 * It is just storing a list of key/value pairs associated to
 * different objects. If you use this table for your model, do not
 * forget to drop the corresponding keys in your preDelete call.
 */
class IDF_Gconf extends Pluf_Model
{
    public $_model = __CLASS__;
    public $datacache = null;
    public $dirty = array();
    public $f = null;
    protected $_mod = null;

    function init()
    {
        $this->_a['table'] = 'idf_gconf';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  //It is automatically added.
                                  'blank' => true, 
                                  ),
                            'model_class' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 150,
                                  'verbose' => __('model class'),
                                  ),
                            'model_id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  'verbose' => __('model id'),
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
        $this->_a['idx'] = array('model_vkey_idx' =>
                                 array(
                                       'col' => 'model_class, model_id, vkey',
                                       'type' => 'unique',
                                       ),
                                 );
        $this->f = new IDF_Config_DataProxy($this);
    }

    function setModel($model)
    {
        $this->datacache = null;
        $this->_mod = $model;
    }

    function initCache()
    {
        $this->datacache = array();
        $this->dirty = array();
        $sql = new Pluf_SQL('model_class=%s AND model_id=%s',
                            array($this->_mod->_model, $this->_mod->id));
        foreach ($this->getList(array('filter' => $sql->gen())) as $val) {
            $this->datacache[$val->vkey] = $val->vdesc;
            $this->dirty[$val->vkey] = $val->id;
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
        if (isset($this->dirty[$key])) {
            // we get to check if deleted by other process + update
            $conf = new IDF_Gconf($this->dirty[$key]);
            if ($conf->id == $this->dirty[$key]) {
                $conf->vdesc = $value;
                $conf->update();
                $this->datacache[$key] = $value;
                return;
            }
        } 
        // we insert
        $conf = new IDF_Gconf();
        $conf->model_class = $this->_mod->_model;
        $conf->model_id = $this->_mod->id;
        $conf->vkey = $key;
        $conf->vdesc = $value;
        $conf->create();
        $this->datacache[$key] = $value;
        $this->dirty[$key] = $conf->id;
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
        $gconf = new IDF_Gconf();
        $sql = new Pluf_SQL('vkey=%s AND model_class=%s AND model_id=%s', array($key, $this->_mod->_model, $this->_mod->id));
        foreach ($gconf->getList(array('filter' => $sql->gen())) as $c) {
            $c->delete();
        }
        if ($initcache) {
            $this->initCache();
        }
    }

    /**
     * Drop the conf of a model.
     *
     * If your model is using this table, just add the following line
     * in your preDelete() method:
     *
     * IDF_Gconf::dropForModel($this)
     *
     * It will take care of the cleaning.
     */
    static public function dropForModel($model)
    {
        $table = Pluf::factory(__CLASS__)->getSqlTable();
        $sql = new Pluf_SQL('model_class=%s AND model_id=%s',
                            array($model->_model, $model->id));
        $db = &Pluf::db();
        $db->execute('DELETE FROM '.$table.' WHERE '.$sql->gen());
    }

    static public function dropUser($signal, &$params)
    {
        self::dropForModel($params['user']);
    }
}
