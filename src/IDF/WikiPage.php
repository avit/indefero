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

Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
Pluf::loadFunction('Pluf_Template_dateAgo');

/**
 * Base definition of a wiki page.
 *
 * A wiki page can have tags and be starred by the users. The real
 * content of the page is stored in the IDF_WikiRevision
 * object. Several revisions are associated to a given page.
 */
class IDF_WikiPage extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_wikipages';
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
                                  'relate_name' => 'wikipages',
                                  ),
                            'title' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('title'),
                                  'help_text' => __('The title of the page must only contain letters, digits or the dash character. For example: My-new-Wiki-Page.'),
                                  ),
                            'summary' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Varchar',
                                  'blank' => false,
                                  'size' => 250,
                                  'verbose' => __('summary'),
                                  'help_text' => __('A one line description of the page content.'),
                                  ),
                            'submitter' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'Pluf_User',
                                  'blank' => false,
                                  'verbose' => __('submitter'),
                                  'relate_name' => 'submitted_wikipages',
                                  ),
                            'interested' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Manytomany',
                                  'model' => 'Pluf_User',
                                  'blank' => true,
                                  'verbose' => __('interested users'),
                                  'help_text' => 'Interested users will get an email notification when the wiki page is changed.',
                                  ),
                            'tags' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Manytomany', 
                                  'blank' => true,
                                  'model' => 'IDF_Tag',
                                  'verbose' => __('labels'),
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
        $table = $this->_con->pfx.'idf_tag_idf_wikipage_assoc';
        $this->_a['views'] = array(
                              'join_tags' => 
                              array(
                                    'join' => 'LEFT JOIN '.$table
                                    .' ON idf_wikipage_id=id',
                                    ),
                                   );
    }

    function __toString()
    {
        return $this->title.' - '.$this->summary;
    }

    function _toIndex()
    {
        $rev = $this->get_current_revision()->_toIndex();
        $str = str_repeat($this->title.' '.$this->summary.' ', 4).' '.$rev;
        return Pluf_Text::cleanString(html_entity_decode($str, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * We drop the information from the timeline.
     */
    function preDelete()
    {
        IDF_Timeline::remove($this);
        IDF_Search::remove($this);
    }

    function get_current_revision() 
    {
        $true = Pluf_DB_BooleanToDb(true, $this->getDbConnection());
        $rev = $this->get_revisions_list(array('filter' => 'is_head='.$true,
                                               'nb' => 1));
        return ($rev->count() == 1) ? $rev[0] : null;
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
        // Note: No indexing is performed here. The indexing is
        // triggered in the postSave step of the revision to ensure
        // that the page as a given revision in the database when
        // doing the indexing.
        if ($create) {
            IDF_Timeline::insert($this, $this->get_project(), 
                                 $this->get_submitter());
        }
    }

    /**
     * Returns an HTML fragment used to display this wikipage in the
     * timeline.
     *
     * The request object is given to be able to check the rights and
     * as such create links to other items etc. You can consider that
     * if displayed, you can create a link to it.
     *
     * @param Pluf_HTTP_Request 
     * @return Pluf_Template_SafeString
     */
    public function timelineFragment($request)
    {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::view', 
                                        array($request->project->shortname,
                                              $this->title));
        $out = '<tr class="log"><td><a href="'.$url.'">'.
            Pluf_esc(Pluf_Template_dateAgo($this->creation_dtime, 'without')).
            '</a></td><td>';
        $stag = new IDF_Template_ShowUser();
        $user = $stag->start($this->get_submitter(), $request, '', false);
        $out .= sprintf(__('<a href="%1$s" title="View page">%2$s</a>, %3$s'), $url, Pluf_esc($this->title), Pluf_esc($this->summary)).'</td>';
        $out .= "\n".'<tr class="extra"><td colspan="2">
<div class="helptext right">'.sprintf(__('Creation of <a href="%s">page&nbsp;%s</a>, by %s'), $url, Pluf_esc($this->title), $user).'</div></td></tr>'; 
        return Pluf_Template::markSafe($out);
    }

    public function feedFragment($request)
    {
        $url = Pluf::f('url_base')
            .Pluf_HTTP_URL_urlForView('IDF_Views_Wiki::view', 
                                      array($request->project->shortname,
                                            $this->title));
        $title = sprintf(__('%s: Documentation page %s added - %s'),
                         $request->project->name,
                         $this->title, $this->summary);
        $date = Pluf_Date::gmDateToGmString($this->creation_dtime);
        $context = new Pluf_Template_Context_Request(
                       $request,
                       array('url' => $url,
                             'title' => $title,
                             'page' => $this,
                             'rev' => $this->get_current_revision(),
                             'create' => true,
                             'date' => $date)
                                                     );
        $tmpl = new Pluf_Template('idf/wiki/feedfragment.xml');
        return $tmpl->render($context);
    }
}