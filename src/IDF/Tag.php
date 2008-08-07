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
 * Store a tag definition.
 *
 * Tags are associated to a project.
 */
define('IDF_TAG_DEFAULT_CLASS', 'other');

class IDF_Tag extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_tags';
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
                            'class' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'default' => IDF_TAG_DEFAULT_CLASS,
                                  'verbose' => __('tag class'),
                                  'help_text' => __('The class of the tag.'),
                                  ),
                            'name' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'verbose' => __('name'),
                                  ),
                            'lcname' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'editable' => false,
                                  'verbose' => __('lcname'),
                                  'help_text' => __('Lower case version of the name for fast searching.'),
                                  ),
                            );

        $this->_a['idx'] =  array(
                                  'lcname_idx' =>
                                  array(
                                        'col' => 'lcname',
                                        'type' => 'normal',
                                        ),
                                  'class_idx' =>
                                  array(
                                        'col' => 'class',
                                        'type' => 'normal',
                                        ),
                                  );
    }

    function preSave($create=false)
    {
        $this->lcname = mb_strtolower($this->name);
    }

    /**
     * Add a tag if not already existing.
     *
     * @param string Name of the tag.
     * @param IDF_Project Project of the tag.
     * @param string Class of the tag (IDF_TAG_DEFAULT_CLASS)
     * @return IDF_Tag The tag.
     */
    public static function add($name, $project, $class=IDF_TAG_DEFAULT_CLASS)
    {
        $class = trim($class);
        $name = trim($name);
        $gtag = new IDF_Tag();
        $sql = new Pluf_SQL('class=%s AND lcname=%s AND project=%s', 
                            array($class, mb_strtolower($name), $project->id));
        $tags = $gtag->getList(array('filter' => $sql->gen()));
        if ($tags->count() < 1) {
            // create a new tag
            $tag = new IDF_Tag();
            $tag->name = $name;
            $tag->class = $class;
            $tag->project = $project;
            $tag->create();
            return $tag;
        }
        return $tags[0];
    }

    function __toString()
    {
        if ($this->class != IDF_TAG_DEFAULT_CLASS) {
            return $this->class.':'.$this->name;
        }
        return $this->name;
    }

}
