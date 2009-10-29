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
 * Base definition of a code review.
 *
 * A code review has a status, submitter, summary, description and is
 * associated to a project.
 *
 * The real content of the review is in the IDF_Review_Patch which
 * contains a given patch and associated comments from reviewers.
 *
 * Basically the hierarchy of the models is:
 * - Review > Patch > Comment > Comment on file
 * 
 * For each review, one can have several patches. Each patch, is
 * getting a series of comments. A comment is tracking the state
 * change in the review (like the issue comments). For each comment,
 * we have a series of file comments. The file comments are associated
 * to the a given modified file in the patch.
 */
class IDF_Review extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_reviews';
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
                                  'relate_name' => 'reviews',
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
                                  'relate_name' => 'submitted_review',
                                  ),
                            'interested' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Manytomany',
                                  'model' => 'Pluf_User',
                                  'blank' => true,
                                  'help_text' => 'Interested users will get an email notification when the review is changed.',
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
        $table = $this->_con->pfx.'idf_review_idf_tag_assoc';
        $this->_a['views'] = array(
                              'join_tags' => 
                              array(
                                    'join' => 'LEFT JOIN '.$table
                                    .' ON idf_review_id=id',
                                    ),
                                   );
    }

    /**
     * Iterate through the patches and comments to get the reviewers.
     */
    function getReviewers()
    {
        $rev = new ArrayObject();
        foreach ($this->get_patches_list() as $p) {
            foreach ($p->get_comments_list() as $c) {
                $rev[] = $c->get_submitter();
            }
        }
        return Pluf_Model_RemoveDuplicates($rev);
    }

    function __toString()
    {
        return $this->id.' - '.$this->summary;
    }

    function _toIndex()
    {
        return '';
    }

    function preDelete()
    {
        IDF_Timeline::remove($this);
        IDF_Search::remove($this);
    }

    function preSave($create=false)
    {
        if ($create) {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
        $this->modif_dtime = gmdate('Y-m-d H:i:s');
    }

    function postSave($create=false)
    {
        // At creation, we index after saving the associated patch.
        if (!$create) IDF_Search::index($this);
    }

    /**
     * Returns an HTML fragment used to display this review in the
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
        return '';
    }

    public function feedFragment($request)
    {
        return '';
    }
}