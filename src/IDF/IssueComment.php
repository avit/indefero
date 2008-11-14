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
        return (is_array($this->changes) and count($this->changes) > 0);
    }

    function _toIndex()
    {
        return $this->content;
    }

    function preSave($create=false)
    {
        if ($this->id == '') {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
    }

    function postSave($create=false)
    {
        if ($create) {
            // Check if more than one comment for this issue. We do
            // not want to insert the first comment in the timeline as
            // the issue itself is inserted.
            $sql = new Pluf_SQL('issue=%s', array($this->issue));
            $co = Pluf::factory('IDF_IssueComment')->getList(array('filter'=>$sql->gen()));
            if ($co->count() > 1) {
                IDF_Timeline::insert($this, $this->get_issue()->get_project(), 
                                     $this->get_submitter());
            }
        }
    }

    public function timelineFragment($request)
    {
        $submitter = $this->get_submitter();
        $issue = $this->get_issue();
        $ic = (in_array($issue->status, $request->project->getTagIdsByStatus('closed'))) ? 'issue-c' : 'issue-o';
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view', 
                                        array($request->project->shortname,
                                              $issue->id));
        return Pluf_Template::markSafe(sprintf(__('<a href="%1$s" class="%2$s" title="View issue">Issue %3$d</a> <em>%4$s</em> updated by %5$s'), $url, $ic, $issue->id, Pluf_esc($issue->summary), Pluf_esc($submitter)));
    }
}
