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
 * A comment to an issue.
 *
 * The first description of an issue is also stored as a comment.
 *
 * A comment is also tracking the changes in the main issue.
 */
class IDF_IssueComment extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_issuecomments';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true, 
                                  ),
                            'issue' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Issue',
                                  'blank' => false,
                                  'verbose' => __('issue'),
                                  'relate_name' => 'comments',
                                  ),
                            'content' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => false,
                                  'verbose' => __('comment'),
                                  ),
                            'submitter' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  'relate_name' => 'commented_issue',
                                  ),
                            'changes' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Serialized',
                                  'blank' => true,
                                  'verbose' => __('changes'),
                                  'help_text' => __('Serialized array of the changes in the issue.'),
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('creation date'),
                                  ),
                            );
        $this->_a['idx'] = array(                           
                            'creation_dtime_idx' =>
                            array(
                                  'col' => 'creation_dtime',
                                  'type' => 'normal',
                                  ),
                            );
    }

    function changedIssue()
    {
        return count($this->changes) > 0;
    }

    function _toIndex()
    {
        return $this->content;
    }

    function preSave()
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
    }

    function postSave()
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

function IDF_IssueComment_Filter($text)
{
    return wordwrap($text, 80, "\n", true);
}