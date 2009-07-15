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
Pluf::loadFunction('Pluf_Shortcuts_RenderToResponse');
Pluf::loadFunction('Pluf_Shortcuts_GetObjectOr404');
Pluf::loadFunction('Pluf_Shortcuts_GetFormForModel');

/**
 * Review views.
 */
class IDF_Views_Review
{
    /**
     * View list of reviews for a given project.
     */
    public $index_precond = array('IDF_Precondition::accessReview');
    public function index($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Code Reviews'), (string) $prj);
        // Paginator to paginate the pages
        $pag = new Pluf_Paginator(new IDF_Review());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the latest reviews.');
        $pag->action = array('IDF_Views_Review::index', array($prj->shortname));
        $otags = $prj->getTagIdsByStatus('open');
        if (count($otags) == 0) $otags[] = 0;
        $pag->forced_where = new Pluf_SQL('project=%s AND status IN ('.implode(', ', $otags).')', array($prj->id));
        $pag->action = array('IDF_Views_Issue::index', array($prj->shortname));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Review_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('title', 'modif_dtime'));
        $pag->items_per_page = 25;
        $pag->no_results_text = __('No reviews were found.');
        $pag->sort_order = array('modif_dtime', 'ASC');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/review/index.html',
                                               array(
                                                     'page_title' => $title,
                                                     'reviews' => $pag,
                                                     ),
                                               $request);
    }

    /**
     * Create a new code review.
     */
    public $create_precond = array('IDF_Precondition::accessReview',
                                   'Pluf_Precondition::loginRequired');
    public function create($request, $match)
    {
        $prj = $request->project;
        $title = __('Start Code Review');
        if ($request->method == 'POST') {
            $form = new IDF_Form_ReviewCreate(array_merge($request->POST,
                                                         $request->FILES),
                                              array('project' => $prj,
                                                    'user' => $request->user
                                                    ));
            if ($form->isValid()) {
                $review = $form->save();
                $urlr = Pluf_HTTP_URL_urlForView('IDF_Views_Review::view', 
                                                 array($prj->shortname, $review->id));
                $request->user->setMessage(sprintf(__('The <a href="%s">code review %d</a> has been created.'), $urlr, $review->id));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Review::index', 
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_ReviewCreate(null,
                                              array('project' => $prj,
                                                    'user' => $request->user));
        }
        return Pluf_Shortcuts_RenderToResponse('idf/review/create.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Download the patch of a review.
     */
    public $getPatch_precond = array('IDF_Precondition::accessReview');
    public function getPatch($request, $match)
    {
        $prj = $request->project;
        $patch = Pluf_Shortcuts_GetObjectOr404('IDF_Review_Patch', $match[2]);
        $prj->inOr404($patch->get_review());
        $file = Pluf::f('upload_issue_path').'/'.$patch->patch;

        $rep = new Pluf_HTTP_Response_File($file, 'text/plain');
        $rep->headers['Content-Disposition'] = 'attachment; filename="'.$patch->id.'.diff"';
        return $rep;

    }

    /**
     * View a code review.
     */
    public $view_precond = array('IDF_Precondition::accessReview');
    public function view($request, $match)
    {
        $prj = $request->project;
        $review = Pluf_Shortcuts_GetObjectOr404('IDF_Review', $match[2]);
        $prj->inOr404($review);
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Review::view',
                                        array($prj->shortname, $review->id));
        $title = Pluf_Template::markSafe(sprintf(__('Review <a href="%s">%d</a>: %s'), $url, $review->id, $review->summary));

        $patches = $review->get_patches_list();
        $patch = $patches[0];
        $diff = new IDF_Diff(file_get_contents(Pluf::f('upload_issue_path').'/'.$patch->patch));
        $diff->parse();
        // The form to submit comments is based on the files in the
        // diff
        if ($request->method == 'POST' and !$request->user->isAnonymous()) {
            $form = new IDF_Form_ReviewFileComment($request->POST,
                                                   array('files' => $diff->files,
                                                         'user' => $request->user,
                                                         'patch' => $patch,
                                                    ));
            if ($form->isValid()) {
                $patch = $form->save();
                $review = $patch->get_review();
                $urlr = Pluf_HTTP_URL_urlForView('IDF_Views_Review::view', 
                                                 array($prj->shortname, $review->id));
                $request->user->setMessage(sprintf(__('Your <a href="%s">code review %d</a> has been published.'), $urlr, $review->id));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Review::index', 
                                                array($prj->shortname));
                // Get the list of reviewers + submitter
                $reviewers = $review->get_reviewers_list();
                if (!Pluf_Model_InArray($review->get_submitter(), $reviewers)) {
                    $reviewers[] = $review->get_submitter();
                }
                $comments = $patch->get_filecomments_list(array('order' => 'id DESC'));
                $context = new Pluf_Template_Context(
                       array(
                             'review' => $review,
                             'patch' => $patch,
                             'comments' => $comments,
                             'project' => $prj,
                             'url_base' => Pluf::f('url_base'),
                             )
                                                     );
                $tmpl = new Pluf_Template('idf/review/review-updated-email.txt');
                $text_email = $tmpl->render($context);
                $email = new Pluf_Mail_Batch(Pluf::f('from_email'));
                $to_emails = array();
                foreach ($reviewers as $user) {
                    if ($user->id != $request->user->id) {
                        $to_emails[] = $user->email;
                    }
                }
                if ('' != $request->conf->getVal('review_notification_email', '')) {
                    $to_emails[] = $request->conf->getVal('review_notification_email');
                }
                foreach ($to_emails as $oemail) {
                    $email->setSubject(sprintf(__('Updated Code Review %s - %s (%s)'),
                                               $review->id, $review->summary, $prj->shortname));
                    $email->setTo($oemail);
                    $email->setReturnPath(Pluf::f('from_email'));
                    $email->addTextMessage($text_email);
                    $email->sendMail();
                }
                $email->close();
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_ReviewFileComment(null,
                                              array('files' => $diff->files,
                                                    'user' => $request->user,
                                                    'patch' => $patch,));
        }
        $scm = IDF_Scm::get($request->project);
        $files = array();
        $reviewers = array();
        foreach ($diff->files as $filename => $def) {
            $fileinfo = $scm->getPathInfo($filename, $patch->get_commit()->scm_id);
            $sql = new Pluf_SQL('cfile=%s', array($filename));
            $cts = $patch->get_filecomments_list(array('filter'=>$sql->gen(),
                                                'order'=>'creation_dtime ASC'));
            foreach ($cts as $ct) {
                $reviewers[] = $ct->get_submitter();
            }
            if (count($def['chunks'])) { 
                $orig_file = ($fileinfo) ? $scm->getFile($fileinfo) : '';
                $files[$filename] = array(
                                          $diff->fileCompare($orig_file, $def, $filename),
                                          $form->f->{md5($filename)},
                                          $cts,
                                          );
            } else {
                $files[$filename] = array('', $form->f->{md5($filename)}, $cts);
            }
        }
        $reviewers = Pluf_Model_RemoveDuplicates($reviewers);
        return Pluf_Shortcuts_RenderToResponse('idf/review/view.html',
                                               array(
                                                     'page_title' => $title,
                                                     'review' => $review,
                                                     'files' => $files,
                                                     'diff' => $diff,
                                                     'patch' => $patch,
                                                     'form' => $form,
                                                     'reviewers' => $reviewers,
                                                     ),
                                               $request);
    }
}

/**
 * Display the summary of an review, then on a new line, display the
 * list of labels with a link to a view "by label only".
 *
 * The summary of the review is linking to the review.
 */
function IDF_Views_Review_SummaryAndLabels($field, $review, $extra='')
{
    $edit = Pluf_HTTP_URL_urlForView('IDF_Views_Review::view', 
                                     array($review->shortname, $review->id));
    $tags = array();
    foreach ($review->get_tags_list() as $tag) {
        $tags[] = Pluf_esc($tag);
    }
    $out = '';
    if (count($tags)) {
        $out = '<br /><span class="label note">'.implode(', ', $tags).'</span>';
    }
    return sprintf('<a href="%s">%s</a>', $edit, Pluf_esc($review->summary)).$out;
}

