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
 * A comment set on a review.
 *
 * A comment is associated to a patch as a review can have many
 * patches associated to it. 
 *
 * A comment is also tracking the changes in the review in the same
 * way the issue comment is tracking the changes in the issue.
 *
 * 
 */
class IDF_Review_Comment extends Pluf_Model
{
    public $_model = __CLASS__;

    function init()
    {
        $this->_a['table'] = 'idf_review_comments';
        $this->_a['model'] = __CLASS__;
        $this->_a['cols'] = array(
                             // It is mandatory to have an "id" column.
                            'id' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Sequence',
                                  'blank' => true, 
                                  ),
                            'patch' => 
                            array(
                                  'type' => 'Pluf_DB_Field_Foreignkey',
                                  'model' => 'IDF_Review_Patch',
                                  'blank' => false,
                                  'verbose' => __('patch'),
                                  'relate_name' => 'comments',
                                  ),
                            'content' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Text',
                                  'blank' => true, // if only commented on lines
                                  'verbose' => __('comment'),
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
                                  'help_text' => 'Serialized array of the changes in the review.',
                                  ),
                            'vote' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Integer',
                                  'default' => 0,
                                  'blank' => true,
                                  'verbose' => __('vote'),
                                  'help_text' => '1, 0 or -1 for positive, neutral or negative vote.',
                                  ),
                            'creation_dtime' =>
                            array(
                                  'type' => 'Pluf_DB_Field_Datetime',
                                  'blank' => true,
                                  'verbose' => __('creation date'),
                                  'index' => true,
                                  ),
                            );
    }

    function changedReview()
    {
        return (is_array($this->changes) and count($this->changes) > 0);
    }

    function _toIndex()
    {
        return $this->content;
    }

    function preDelete()
    {
        IDF_Timeline::remove($this);
    }

    function preSave($create=false)
    {
        if ($create) {
            $this->creation_dtime = gmdate('Y-m-d H:i:s');
        }
    }

    function postSave($create=false)
    {
        if (0 and $create) {
            // Check if more than one comment for this patch. We do
            // not want to insert the first comment in the timeline as
            // the patch itself is inserted.
            $sql = new Pluf_SQL('patch=%s', array($this->patch));
            $co = Pluf::factory(__CLASS__)->getList(array('filter'=>$sql->gen()));
            if ($co->count() > 1) {
                IDF_Timeline::insert($this, $this->get_patch()->get_review()->get_project(), 
                                     $this->get_submitter());
            }
        }
        IDF_Search::index($this->get_patch()->get_review());
    }

    public function timelineFragment($request)
    {
        return '';
    }

    public function feedFragment($request)
    {
        return '';
    }

    /**
     * Notify of the update of the review.
     *
     *
     * @param IDF_Conf Current configuration
     * @param bool Creation (true)
     */
    public function notify($conf, $create=true)
    {
        $patch = $this->get_patch();
        $review = $patch->get_review();
        $prj = $review->get_project();
        $to_email = array();
        if ('' != $conf->getVal('review_notification_email', '')) {
            $langs = Pluf::f('languages', array('en'));
            $to_email[] = array($conf->getVal('issues_notification_email'),
                                $langs[0]);
        }
        $current_locale = Pluf_Translation::getLocale();
        $reviewers = $review->getReviewers();
        if (!Pluf_Model_InArray($review->get_submitter(), $reviewers)) {
            $reviewers[] = $review->get_submitter();
        }
        $comments = $patch->getFileComments(array('order' => 'id DESC'));
        $context = new Pluf_Template_Context(
                       array(
                             'review' => $review,
                             'patch' => $patch,
                             'comments' => $comments,
                             'project' => $prj,
                             'url_base' => Pluf::f('url_base'),
                             )
                                             );
        // build the list of emails and lang
        foreach ($reviewers as $user) {
            $email_lang = array($user->email,
                                $user->language);
            if (!in_array($email_lang, $to_email)) {
                $to_email[] = $email_lang;
            }
        }
        $tmpl = new Pluf_Template('idf/review/review-updated-email.txt');
        foreach ($to_email as $email_lang) {
            Pluf_Translation::loadSetLocale($email_lang[1]);
            $email = new Pluf_Mail(Pluf::f('from_email'), $email_lang[0],
                                   sprintf(__('Updated Code Review %s - %s (%s)'),
                                           $review->id, $review->summary, $prj->shortname));

            $email->addTextMessage($tmpl->render($context));
            $email->sendMail();
        }
        Pluf_Translation::loadSetLocale($current_locale);
    }
}
