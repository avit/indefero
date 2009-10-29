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
        if ($create) {
            IDF_Timeline::insert($this, 
                                 $this->get_patch()->get_review()->get_project(), 
                                 $this->get_submitter());
        }
    }

    public function timelineFragment($request)
    {
        $review = $this->get_patch()->get_review();
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Review::view', 
                                        array($request->project->shortname,
                                              $review->id));
        $out = '<tr class="log"><td><a href="'.$url.'">'.
            Pluf_esc(Pluf_Template_dateAgo($this->creation_dtime, 'without')).
            '</a></td><td>';
        $stag = new IDF_Template_ShowUser();
        $user = $stag->start($this->get_submitter(), $request, '', false);
        $ic = (in_array($review->status, $request->project->getTagIdsByStatus('closed'))) ? 'issue-c' : 'issue-o';
        $out .= sprintf(__('<a href="%1$s" class="%2$s" title="View review">Review %3$d</a>, %4$s'), $url, $ic, $review->id, Pluf_esc($review->summary)).'</td>';
        $out .= "\n".'<tr class="extra"><td colspan="2">
<div class="helptext right">'.sprintf(__('Update of <a href="%s" class="%s">review&nbsp;%d</a>, by %s'), $url, $ic, $review->id, $user).'</div></td></tr>'; 
        return Pluf_Template::markSafe($out);
    }

    public function feedFragment($request)
    {
        $review = $this->get_patch()->get_review();
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Review::view', 
                                        array($request->project->shortname,
                                              $review->id));
        $title = sprintf(__('%s: Updated review %d - %s'),
                         Pluf_esc($request->project->name),
                         $review->id, Pluf_esc($review->summary));
        $url .= '#ic'.$this->id;
        $date = Pluf_Date::gmDateToGmString($this->creation_dtime);
        $context = new Pluf_Template_Context_Request(
                       $request,
                       array('url' => $url,
                             'author' => $this->get_submitter(),
                             'title' => $title,
                             'c' => $this,
                             'review' => $review,
                             'date' => $date)
                                                     );
        $tmpl = new Pluf_Template('idf/review/feedfragment.xml');
        return $tmpl->render($context);
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
        $gcomments = $patch->get_comments_list(array('order' => 'id DESC'));
        $context = new Pluf_Template_Context(
                       array(
                             'review' => $review,
                             'patch' => $patch,
                             'comments' => $comments,
                             'gcomments' => $gcomments,
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
