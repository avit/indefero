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
 * A revision of a wiki page.
 *
 */
class IDF_WikiRevision extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_wikirevisions';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true, 
                                  ),
                            'wikipage' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_WikiPage',
                                  'blank' => false,
                                  'verbose' => __('page'),
                                  'relate_name' => 'revisions',
                                  ),
                            'is_head' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Boolean',
                                  'blank' => false,
                                  'default' => false,
                                  'help_text' => 'If this revision is the latest, we mark it as being the head revision.',
                                  'index' => true,
                            
                                  ),
                            'summary' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('summary'),
                                  'help_text' => __('A one line description of the changes.'),
                                  ),
                            'content' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Compressed',
                                  'blank' => false,
                                  'verbose' => __('content'),
                                  ),
                            'submitter' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  ),
                            'changes' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Serialized',
                                  'blank' => true,
                                  'verbose' => __('changes'),
                                  'help_text' => 'Serialized array of the changes in the issue.',
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

    function changedRevision()
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
            $this->is_head = true;
        }
    }

    function postSave($create=false)
    {
        if ($create) {
            // Check if more than one revision for this page. We do
            // not want to insert the first revision in the timeline
            // as the page itself is inserted.  We do not insert on
            // update as update is performed to change the is_head
            // flag.
            $sql = new Pluf_SQL('wikipage=%s', array($this->wikipage));
            $rev = Pluf::factory('IDF_WikiRevision')->getList(array('filter'=>$sql->gen()));
            if ($rev->count() > 1) {
                IDF_Timeline::insert($this, $this->get_wikipage()->get_project(), 
                                     $this->get_submitter());
                foreach ($rev as $r) {
                    if ($r->id != $this->id and $r->is_head) {
                        $r->is_head = false;
                        $r->update();
                    }
                }
            }
            $page = $this->get_wikipage();
            $page->update(); // Will update the modification timestamp.
            IDF_Search::index($page);
        }
    }

    public function timelineFragment($request)
    {
        $page = $this->get_wikipage();
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::view', 
                                        array($request->project->shortname,
                                              $page->title));
        $out = "\n".'<tr class="log"><td><a href="'.$url.'">'.
            Pluf_esc(Pluf_Template_dateAgo($this->creation_dtime, 'without')).
            '</a></td><td>';
        $submitter = $this->get_submitter();
        $out .= sprintf(__('<a href="%1$s" title="View page">%2$s</a>, %3$s'), $url, Pluf_esc($page->title), Pluf_esc($this->summary));
        //$out .= '<strong>'.__('Summary:').'</strong>&nbsp;'.Pluf_esc($this->summary).' ';
        if ($this->changedRevision()) {
            $out .= '<div class="issue-changes-timeline">';
            foreach ($this->changes as $w => $v) {
                $out .= '<strong>';
                switch ($w) {
                case 'lb':
                    $out .= __('Labels:'); break;
                }
                $out .= '</strong>&nbsp;';
                if ($w == 'lb') {
                    $out .= Pluf_esc(implode(', ', $v));
                } else {
                    $out .= Pluf_esc($v);
                }
                $out .= ' ';
            }
            $out .= '</div>';
        }
        $out .= '</td></tr>';
        $out .= "\n".'<tr class="extra"><td colspan="2">
<div class="helptext right">'.sprintf(__('Change of <a href="%s">%s</a>'), $url, Pluf_esc($page->title)).', '.__('by').' '.Pluf_esc($submitter).'</div></td></tr>'; 
        return Pluf_Template::markSafe($out);
    }
}
