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
 * Log of what is going on in the project.
 *
 * We log here what is going on. It is important that creation_dtime
 * must be set at the *real* date time of the creation of the object,
 * which is not necessarily the date of the insert in the
 * database. For example, code can be created 3 days ago and committed
 * in the main repository.
 *
 * The public_dtime is the date at which the information is being made
 * public and here that would be the commit date time of the code.
 *
 */
class IDF_Timeline extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_timeline';
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
                                  'relate_name' => 'thumbroll',
                                  ),
                            'author' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'is_null' => true,
                                  'help_text' => 'This will allow us to list the latest commits of a user in its profile.',
                                  ),
                            'model_class' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 150,
                                  ),
                            'model_id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'blank' => false,
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'index' => true,
                                  ),
                            'public_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'index' => true,
                                  ),
                            );
    }

    function __toString()
    {
        return $this->summary.' - ('.$this->scm_id.')';
    }

    function _toIndex()
    {
        $str = str_repeat($this->summary.' ', 4).' '.$this->fullmessage;
        return Pluf_Text::cleanString(html_entity_decode($str, ENT_QUOTES, 'UTF-8'));
    }

    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->public_dtime = gmdate('Y-m-d H:i:s');
        }
        if ($this->creation_dtime == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
    }

    /**
     * Easily insert an item in the timeline.
     *
     * @param mixed Item to be inserted
     * @param IDF_Project Project of the item
     * @param Pluf_User Author of the item (null)
     * @param string GMT creation date time (null)
     * @return bool Success
     */
    public static function insert($item, $project, $author=null, $creation=null)
    {
        $t = new IDF_Timeline();
        $t->project = $project;
        $t->author = $author;
        $t->creation_dtime = (is_null($creation)) ? '' : $creation;
        $t->model_id = $item->id;
        $t->model_class = $item->_model;
        $t->create();
        return $t;
    }

    /**
     * Remove an item from the timeline.
     *
     * You must call this function when you delete items wich are
     * tracked in the timeline. Just add the call:
     *
     * IDF_Timeline::remove($this);
     *
     * in the preDelete() method of your object.
     *
     * @param mixed Item to be removed
     * @return bool Success
     */
    public static function remove($item)
    {
        if ($item->id > 0) {
            $sql = new Pluf_SQL('model_id=%s AND model_class=%s',
                                array($item->id, $item->_model));
            $items = Pluf::factory('IDF_Timeline')->getList(array('filter'=>$sql->gen()));
            foreach ($items as $tl) {
                $tl->delete();
            }
        }
        return true;
    }
}
