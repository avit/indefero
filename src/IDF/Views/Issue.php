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
 * Issues' views.
 */
class IDF_Views_Issue
{
    /**
     * View list of issues for a given project.
     */
    public $index_precond = array('IDF_Precondition::accessIssues');
    public function index($request, $match, $api=false)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Open Issues'), (string) $prj);
        // Get stats about the issues
        $open = $prj->getIssueCountByStatus('open');
        $closed = $prj->getIssueCountByStatus('closed');
        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the open issues.');
        $otags = $prj->getTagIdsByStatus('open');
        if (count($otags) == 0) $otags[] = 0;
        $pag->forced_where = new Pluf_SQL('project=%s AND status IN ('.implode(', ', $otags).')', array($prj->id));
        $pag->action = array('IDF_Views_Issue::index', array($prj->shortname));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        $params = array('project' => $prj,
                        'page_title' => $title,
                        'open' => $open,
                        'closed' => $closed,
                        'issues' => $pag,
                        'cloud' => 'issues');
        if ($api) return $params;
        return Pluf_Shortcuts_RenderToResponse('idf/issues/index.html',
                                               $params, $request);
    }

    /**
     * View the issues of a given user. 
     *
     * Only open issues are shown.
     */
    public $myIssues_precond = array('IDF_Precondition::accessIssues',
                                     'Pluf_Precondition::loginRequired');
    public function myIssues($request, $match)
    {
        $prj = $request->project;
        $otags = $prj->getTagIdsByStatus('open');
        if (count($otags) == 0) $otags[] = 0;
        if ($match[2] == 'submit') {
            $title = sprintf(__('My Submitted %s Issues'), (string) $prj);
            $f_sql = new Pluf_SQL('project=%s AND submitter=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $request->user->id));
        } else {
            $title = sprintf(__('My Working %s Issues'), (string) $prj);
            $f_sql = new Pluf_SQL('project=%s AND owner=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $request->user->id));
        }
        // Get stats about the issues
        $sql = new Pluf_SQL('project=%s AND submitter=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $request->user->id));
        $nb_submit = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));
        $sql = new Pluf_SQL('project=%s AND owner=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $request->user->id));
        $nb_owner = Pluf::factory('IDF_Issue')->getCount(array('filter'=>$sql->gen()));
        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the open issues.');
        $pag->forced_where = $f_sql;
        $pag->action = array('IDF_Views_Issue::myIssues', array($prj->shortname, $match[2]));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/my-issues.html',
                                               array('project' => $prj,
                                                     'page_title' => $title,
                                                     'nb_submit' => $nb_submit,
                                                     'nb_owner' => $nb_owner,
                                                     'issues' => $pag,
                                                     ),
                                               $request);
    }

    public $create_precond = array('IDF_Precondition::accessIssues',
                                   'Pluf_Precondition::loginRequired');
    public function create($request, $match, $api=false)
    {
        $prj = $request->project;
        $title = __('Submit a new issue');
        $params = array(
                        'project' => $prj,
                        'user' => $request->user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_IssueCreate(array_merge($request->POST,
                                                         $request->FILES),
                                             $params);
            if ($form->isValid()) {
                $issue = $form->save();
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::index',
                                                array($prj->shortname));
                $urlissue = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                         array($prj->shortname, $issue->id));
                $request->user->setMessage(sprintf(__('<a href="%s">Issue %d</a> has been created.'), $urlissue, $issue->id));
                if (null != $issue->get_owner() and $issue->owner != $issue->submitter) {
                    $comments = $issue->get_comments_list(array('order' => 'id ASC'));
                    $context = new Pluf_Template_Context(
                            array(
                                  'issue' => $issue,
                                  'comment' => $comments[0],
                                  'project' => $prj,
                                  'url_base' => Pluf::f('url_base'),
                                  )
                                                         );
                    $oemail = $issue->get_owner()->email;
                    $email = new Pluf_Mail(Pluf::f('from_email'), $oemail,
                                           sprintf(__('Issue %s - %s (%s)'),
                                                   $issue->id, $issue->summary, $prj->shortname));
                    $tmpl = new Pluf_Template('idf/issues/issue-created-email.txt');
                    $email->addTextMessage($tmpl->render($context));
                    $email->sendMail();
                }
                if ($api) return $issue;
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_IssueCreate(null, $params);
        }
        $params = array_merge(
                              array('project' => $prj,
                                    'form' => $form,
                                    'page_title' => $title,
                                    ),
                              self::autoCompleteArrays($prj)
                              );
        if ($api == true) return $params;
        return Pluf_Shortcuts_RenderToResponse('idf/issues/create.html',
                                               $params, $request);
    }

    public $search_precond = array('IDF_Precondition::accessIssues');
    public function search($request, $match)
    {
        $prj = $request->project;
        if (!isset($request->REQUEST['q']) or trim($request->REQUEST['q']) == '') {
            $url =  Pluf_HTTP_URL_urlForView('IDF_Views_Issue::index', 
                                             array($prj->shortname));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $q = $request->REQUEST['q'];
        $title = sprintf(__('Search Issues - %s'), Pluf_esc($q));
        $issues = new Pluf_Search_ResultSet(IDF_Search::mySearch($q, $prj));
        if (count($issues) > 100) {
            // no more than 100 results as we do not care
            $issues->results = array_slice($issues->results, 0, 100);
        }
        $pag = new Pluf_Paginator();
        $pag->items = $issues;
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the found issues.');
        $pag->action = array('IDF_Views_Issue::search', array($prj->shortname), array('q'=> $q));
        $list_display = array(
                              'id' => __('Id'),
                              array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
                              array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
                              array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display);
        $pag->items_per_page = 100;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        $params = array('page_title' => $title,
                        'issues' => $pag,
                        'q' => $q,
                        );
        return Pluf_Shortcuts_RenderToResponse('idf/issues/search.html', $params, $request);

    }

    public $view_precond = array('IDF_Precondition::accessIssues');
    public function view($request, $match)
    {
        $prj = $request->project;
        $issue = Pluf_Shortcuts_GetObjectOr404('IDF_Issue', $match[2]);
        $prj->inOr404($issue);
        $comments = $issue->get_comments_list(array('order' => 'id ASC'));
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                        array($prj->shortname, $issue->id));
        $title = Pluf_Template::markSafe(sprintf(__('Issue <a href="%s">%d</a>: %s'), $url, $issue->id, $issue->summary));
        $form = false; // The form is available only if logged in.
        $starred = false;
        $closed = in_array($issue->status, $prj->getTagIdsByStatus('closed'));
        if (!$request->user->isAnonymous()) {
            $starred = Pluf_Model_InArray($request->user, $issue->get_interested_list());
            $params = array(
                            'project' => $prj,
                            'user' => $request->user,
                            'issue' => $issue,
                            );
            if ($request->method == 'POST') {
                $form = new IDF_Form_IssueUpdate(array_merge($request->POST, 
                                                             $request->FILES),
                                                 $params);
                if ($form->isValid()) {
                    $issue = $form->save();
                    $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::index',
                                                    array($prj->shortname));
                    $urlissue = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                                         array($prj->shortname, $issue->id));
                    $request->user->setMessage(sprintf(__('<a href="%s">Issue %d</a> has been updated.'), $urlissue, $issue->id));
                    // Get the list of interested person + owner + submitter
                    $interested = $issue->get_interested_list();
                    if (!Pluf_Model_InArray($issue->get_submitter(), $interested)) {
                        $interested[] = $issue->get_submitter();
                    }
                    if (null != $issue->get_owner() and 
                        !Pluf_Model_InArray($issue->get_owner(), $interested)) {
                        $interested[] = $issue->get_owner();
                    }
                    $comments = $issue->get_comments_list(array('order' => 'id DESC'));
                    $context = new Pluf_Template_Context(
                            array(
                                  'issue' => $issue,
                                  'comments' => $comments,
                                  'project' => $prj,
                                  'url_base' => Pluf::f('url_base'),
                                  )
                                                         );
                    $tmpl = new Pluf_Template('idf/issues/issue-updated-email.txt');
                    $text_email = $tmpl->render($context);
                    $email = new Pluf_Mail_Batch(Pluf::f('from_email'));
                    foreach ($interested as $user) {
                        if ($user->id != $request->user->id) {
                            $email->setSubject(sprintf(__('Updated Issue %s - %s (%s)'),
                                                       $issue->id, $issue->summary, $prj->shortname));
                            $email->setTo($user->email);
                            $email->setReturnPath(Pluf::f('from_email'));
                            $email->addTextMessage($text_email);
                            $email->sendMail();
                        }
                    }
                    $email->close();
                    return new Pluf_HTTP_Response_Redirect($url);
                }
            } else {
                $form = new IDF_Form_IssueUpdate(null, $params);
            }
        }
        $arrays = self::autoCompleteArrays($prj);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/view.html',
                                               array_merge(
                                               array(
                                                     'issue' => $issue,
                                                     'comments' => $comments,
                                                     'form' => $form,
                                                     'starred' => $starred,
                                                     'page_title' => $title,
                                                     'closed' => $closed,
                                                     ),
                                               $arrays),
                                               $request);
    }


    /**
     * Download a given attachment.
     */
    public $getAttachment_precond = array('IDF_Precondition::accessIssues');
    public function getAttachment($request, $match)
    {
        $prj = $request->project;
        $attach = Pluf_Shortcuts_GetObjectOr404('IDF_IssueFile', $match[2]);
        $prj->inOr404($attach->get_comment()->get_issue());
        $res = new Pluf_HTTP_Response_File(Pluf::f('upload_issue_path').'/'.$attach->attachment,
                                           'application/octet-stream');
        $res->headers['Content-Disposition'] = 'attachment; filename="'.$attach->filename.'"';
        return $res;
    }

    /**
     * View list of issues for a given project with a given status.
     */
    public $listStatus_precond = array('IDF_Precondition::accessIssues');
    public function listStatus($request, $match)
    {
        $prj = $request->project;
        $status = $match[2];
        $title = sprintf(__('%s Closed Issues'), (string) $prj);
        // Get stats about the issues
        $open = $prj->getIssueCountByStatus('open');
        $closed = $prj->getIssueCountByStatus('closed');
        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = __('This table shows the closed issues.');
        $otags = $prj->getTagIdsByStatus('closed');
        if (count($otags) == 0) $otags[] = 0;
        $pag->forced_where = new Pluf_SQL('project=%s AND status IN ('.implode(', ', $otags).')', array($prj->id));
        $pag->action = array('IDF_Views_Issue::listStatus', array($prj->shortname, $status));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/issues/index.html',
                                               array('project' => $prj,
                                                     'page_title' => $title,
                                                     'open' => $open,
                                                     'closed' => $closed,
                                                     'issues' => $pag,
                                                     'cloud' => 'closed_issues',
                                                     ),
                                               $request);
    }

    /**
     * View list of issues for a given project with a given label.
     */
    public $listLabel_precond = array('IDF_Precondition::accessIssues');
    public function listLabel($request, $match)
    {
        $prj = $request->project;
        $tag = Pluf_Shortcuts_GetObjectOr404('IDF_Tag', $match[2]);
        $status = $match[3]; 
        if ($tag->project != $prj->id or !in_array($status, array('open', 'closed'))) {
            throw new Pluf_HTTP_Error404();
        }
        if ($status == 'open') {
            $title = sprintf(__('%1$s Issues with Label %2$s'), (string) $prj,
                             (string) $tag);
        } else {
            $title = sprintf(__('%1$s Closed Issues with Label %2$s'), 
                             (string) $prj, (string) $tag);
        }
        // Get stats about the open/closed issues having this tag.
        $open = $prj->getIssueCountByStatus('open', $tag);
        $closed = $prj->getIssueCountByStatus('closed', $tag);
        // Paginator to paginate the issues
        $pag = new Pluf_Paginator(new IDF_Issue());
        $pag->model_view = 'join_tags';
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('project_m' => $prj,
                                       'shortname' => $prj->shortname,
                                       'current_user' => $request->user);
        $pag->summary = sprintf(__('This table shows the issues with label %s.'), (string) $tag);
        $otags = $prj->getTagIdsByStatus($status);
        if (count($otags) == 0) $otags[] = 0;
        $pag->forced_where = new Pluf_SQL('project=%s AND idf_tag_id=%s AND status IN ('.implode(', ', $otags).')', array($prj->id, $tag->id));
        $pag->action = array('IDF_Views_Issue::listLabel', array($prj->shortname, $tag->id, $status));
        $pag->sort_order = array('modif_dtime', 'ASC'); // will be reverted
        $pag->sort_reverse_order = array('modif_dtime');
        $list_display = array(
             'id' => __('Id'),
             array('summary', 'IDF_Views_Issue_SummaryAndLabels', __('Summary')),
             array('status', 'IDF_Views_Issue_ShowStatus', __('Status')),
             array('modif_dtime', 'Pluf_Paginator_DateAgo', __('Last Updated')),
                              );
        $pag->configure($list_display, array(), array('status', 'modif_dtime'));
        $pag->items_per_page = 10;
        $pag->no_results_text = __('No issues were found.');
        $pag->setFromRequest($request);
        if (($open+$closed) > 0) {
            $completion = sprintf('%01.0f%%', (100*$closed)/((float) $open+$closed));
        } else {
            $completion = false;
        }
        return Pluf_Shortcuts_RenderToResponse('idf/issues/by-label.html',
                                               array('project' => $prj,
                                                     'completion' => $completion,
                                                     'page_title' => $title,
                                                     'open' => $open,
                                                     'label' => $tag,
                                                     'closed' => $closed,
                                                     'issues' => $pag,
                                                     ),
                                               $request);
    }

    /**
     * Star/Unstar an issue.
     */
    public $star_precond = array('IDF_Precondition::accessIssues',
                                 'Pluf_Precondition::loginRequired');
    public function star($request, $match)
    {
        $prj = $request->project;
        $issue = Pluf_Shortcuts_GetObjectOr404('IDF_Issue', $match[2]);
        $prj->inOr404($issue);
        if ($request->method == 'POST') {
            $starred = Pluf_Model_InArray($request->user, $issue->get_interested_list());
            if ($starred) {
                $issue->delAssoc($request->user);
                $request->user->setMessage(__('The issue has been removed from your watch list.'));
            } else {
                $issue->setAssoc($request->user);
                $request->user->setMessage(__('The issue has been added to your watch list.'));
            }
        }
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view',
                                        array($prj->shortname, $issue->id));
        return new Pluf_HTTP_Response_Redirect($url);
    }

    /**
     * Create the autocomplete arrays for the little AJAX stuff.
     */
    public static function autoCompleteArrays($project)
    {
        $conf = new IDF_Conf();
        $conf->setProject($project);
        $auto = array('auto_status' => '', 'auto_labels' => '');
        $auto_raw = array('auto_status' => '', 'auto_labels' => '');
        $st = $conf->getVal('labels_issue_open', IDF_Form_IssueTrackingConf::init_open);
        $st .= "\n".$conf->getVal('labels_issue_closed', IDF_Form_IssueTrackingConf::init_closed);
        $auto_raw['auto_status'] = $st;
        $auto_raw['auto_labels'] = $conf->getVal('labels_issue_predefined', IDF_Form_IssueTrackingConf::init_predefined);
        foreach ($auto_raw as $key => $st) {
            $st = preg_split("/\015\012|\015|\012/", $st, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($st as $s) {
                $v = '';
                $d = '';
                $_s = split('=', $s, 2);
                if (count($_s) > 1) {
                    $v = trim($_s[0]);
                    $d = trim($_s[1]);
                } else {
                    $v = trim($_s[0]);
                }
                $auto[$key] .= sprintf('{ name: "%s", to: "%s" }, ',
                                       Pluf_esc($d),
                                       Pluf_esc($v));
            }
            $auto[$key] = substr($auto[$key], 0, -1);
        }
        // Get the members/owners
        $m = $project->getMembershipData();
        $auto['_auto_owner'] = $m['members'];
        $auto['auto_owner'] = '';
        foreach ($m['owners'] as $owner) {
            if (!Pluf_Model_InArray($owner, $auto['_auto_owner'])) {
                $auto['_auto_owner'][] = $owner;
            }
        }
        foreach ($auto['_auto_owner'] as $owner) {
            $auto['auto_owner'] .= sprintf('{ name: "%s", to: "%s" }, ',
                                           Pluf_esc($owner),
                                           Pluf_esc($owner->login));
        }
        $auto['auto_owner'] = substr($auto['auto_owner'], 0, -1);
        unset($auto['_auto_owner']);
        return $auto;
    }
}

/**
 * Display the summary of an issue, then on a new line, display the
 * list of labels with a link to a view "by label only".
 *
 * The summary of the issue is linking to the issue.
 */
function IDF_Views_Issue_SummaryAndLabels($field, $issue, $extra='')
{
    $edit = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view', 
                                     array($issue->shortname, $issue->id));
    $tags = array();
    foreach ($issue->get_tags_list() as $tag) {
        $url = Pluf_HTTP_URL_urlForView('IDF_Views_Issue::listLabel', 
                                        array($issue->shortname, $tag->id, 'open'));
        $tags[] = sprintf('<a class="label" href="%s">%s</a>', $url, Pluf_esc((string) $tag));
    }
    $s = '';
    if (!$issue->current_user->isAnonymous() and
        Pluf_Model_InArray($issue->current_user, $issue->get_interested_list())) {
        $s = '<img style="vertical-align: text-bottom;" src="'.Pluf_Template_Tag_MediaUrl::url('/idf/img/star.png').'" alt="'.__('On your watch list.').'" /> ';
    }
    $out = '';
    if (count($tags)) {
        $out = '<br /><span class="note">'.implode(', ', $tags).'</span>';
    }
    return $s.sprintf('<a href="%s">%s</a>', $edit, Pluf_esc($issue->summary)).$out;
}

/**
 * Display the status in the issue listings.
 *
 */
function IDF_Views_Issue_ShowStatus($field, $issue, $extra='')
{
    return Pluf_esc($issue->get_status()->name);
}