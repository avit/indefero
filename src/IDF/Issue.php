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
 * Base definition of an issue.
 *
 * An issue can have labels, comments, can be starred by people.
 */
class IDF_Issue extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_issues';
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
                                  'verbose' => __('project'),
                                  'relate_name' => 'issues',
                                  ),
                            'summary' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('summary'),
                                  ),
                            'submitter' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  'relate_name' => 'submitted_issue',
                                  ),
                            'owner' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => true, // no owner when submitted.
                                  'is_null' => true,
                                  'verbose' => __('owner'),
                                  'relate_name' => 'owned_issue',
                                  ),
                            'interested' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Manytomany',
                                  'model' => 'Pluf_User',
                                  'blank' => true,
                                  'verbose' => __('interested users'),
                                  'help_text' => __('Interested users will get an email notification when the issue is changed.'),
                                  ),
                            'tags' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Manytomany', 
                                  'blank' => true,
                                  'model' => 'IDF_Tag',
                                  'verbose' => __('labels'),
                                  ),
                            'status' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey', 
                                  'blank' => false,
                                  'model' => 'IDF_Tag',
                                  'verbose' => __('status'),
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('creation date'),
                                  ),
                            'modif_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('modification date'),
                                  ),
                            );
        $this->_a['idx'] = array(                           
                            'modif_dtime_idx' =>
                            array(
                                  'col' => 'modif_dtime',
                                  'type' => 'normal',
                                  ),
                            );
        $table = $this->_con->pfx.'idf_issue_idf_tag_assoc';
        $this->_a['views'] = array(
                              'join_tags' => 
                              array(
                                    'join' => 'LEFT JOIN '.$table
                                    .' ON idf_issue_id=id',
                                    ),
                                   );
    }

    function __toString()
    {
        return $this->id.' - '.$this->summary;
    }

    function _toIndex()
    {
        return '';
    }


    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
        $this->modif_dtime = gmdate('Y-m-d H:i:s');
    }

    function postSave($create=false)
    {
        // This will be used to fire the indexing or send a
        // notification email to the interested people, etc.
        $q = new Pluf_Queue();
        $q->model_class = __CLASS__;
        $q->model_id = $this->id;
        $q->action = 'updated';
        $q->lock = 0;
        $q->create();
    }
}