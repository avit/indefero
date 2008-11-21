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
 * Project's views.
 */
class IDF_Views_Project
{
    /**
     * Home page of a project.
     */
    public $home_precond = array('IDF_Precondition::baseAccess');
    public function home($request, $match)
    {
        $prj = $request->project;
        $team = $prj->getMembershipData();
        $title = (string) $prj;
        $downloads = array();
        if ($request->rights['hasDownloadsAccess']) {
            $tags = IDF_Views_Download::getDownloadTags($prj);
            // the first tag is the featured, the last is the deprecated.
            $downloads = $tags[0]->get_idf_upload_list(); 
        }
        return Pluf_Shortcuts_RenderToResponse('idf/project/home.html',
                                               array(
                                                     'page_title' => $title,
                                                     'team' => $team,
                                                     'downloads' => $downloads,
                                                     ),
                                               $request);
    }

    /**
     * Timeline of the project.
     */
    public $timeline_precond = array('IDF_Precondition::baseAccess');
    public function timeline($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Updates'), (string) $prj);
        $team = $prj->getMembershipData();

        $pag = new IDF_Timeline_Paginator(new IDF_Timeline());
        $pag->class = 'recent-issues';
        $pag->item_extra_props = array('request' => $request);
        $pag->summary = __('This table shows the project updates.');
        // Need to check the rights
        $rights = array();
        if (true === IDF_Precondition::accessSource($request)) {
            $rights[] = '\'IDF_Commit\'';
        }
        if (true === IDF_Precondition::accessIssues($request)) {
            $rights[] = '\'IDF_Issue\'';
            $rights[] = '\'IDF_IssueComment\'';
        }
        if (true === IDF_Precondition::accessDownloads($request)) {
            $rights[] = '\'IDF_Upload\'';
        }
        if (count($rights) == 0) {
            $rights[] = '\'IDF_Dummy\'';
        }
        $sql = sprintf('model_class IN (%s)', implode(', ', $rights));
        $pag->forced_where = new Pluf_SQL('project=%s AND '.$sql, 
                                          array($prj->id));
        $pag->sort_order = array('creation_dtime', 'ASC');
        $pag->sort_reverse_order = array('creation_dtime');
        $pag->action = array('IDF_Views_Project::timeline', array($prj->shortname));
        $list_display = array(
             'creation_dtime' => __('Age'),
             'id' => __('Change'),
                              );
        $pag->configure($list_display, array(), array('creation_dtime'));
        $pag->items_per_page = 20;
        $pag->no_results_text = __('No changes were found.');
        $pag->setFromRequest($request);
        $downloads = array();
        if ($request->rights['hasDownloadsAccess']) {
            $tags = IDF_Views_Download::getDownloadTags($prj);
            // the first tag is the featured, the last is the deprecated.
            $downloads = $tags[0]->get_idf_upload_list(); 
        }
        return Pluf_Shortcuts_RenderToResponse('idf/project/timeline.html',
                                               array(
                                                     'page_title' => $title,
                                                     'timeline' => $pag,
                                                     'team' => $team,
                                                     'downloads' => $downloads,
                                                     ),
                                               $request);
    }


    /**
     * Administrate the summary of a project.
     */
    public $admin_precond = array('IDF_Precondition::projectOwner');
    public function admin($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Project Summary'), (string) $prj);
        $form_fields = array('fields'=> array('name', 'description'));
        if ($request->method == 'POST') {
            $form = Pluf_Shortcuts_GetFormForModel($prj, $request->POST,
                                                   $form_fields);
            if ($form->isValid()) {
                $prj = $form->save();
                $request->user->setMessage(__('The project has been updated.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::admin', 
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = Pluf_Shortcuts_GetFormForModel($prj, $prj->getData(),
                                                   $form_fields);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/summary.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Administrate the issue tracking of a project.
     */
    public $adminIssues_precond = array('IDF_Precondition::projectOwner');
    public function adminIssues($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Issue Tracking Configuration'), (string) $prj);
        $conf = new IDF_Conf();
        $conf->setProject($prj);
        if ($request->method == 'POST') {
            $form = new IDF_Form_IssueTrackingConf($request->POST);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    $conf->setVal($key, $val);
                }
                $request->user->setMessage(__('The issue tracking configuration has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminIssues',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $keys = array('labels_issue_open', 'labels_issue_closed',
                          'labels_issue_predefined', 'labels_issue_one_max');
            foreach ($keys as $key) {
                $_val = $conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_IssueTrackingConf($params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/issue-tracking.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Administrate the downloads of a project.
     */
    public $adminDownloads_precond = array('IDF_Precondition::projectOwner');
    public function adminDownloads($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Downloads Configuration'), (string) $prj);
        $conf = new IDF_Conf();
        $conf->setProject($prj);
        if ($request->method == 'POST') {
            $form = new IDF_Form_UploadConf($request->POST);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    $conf->setVal($key, $val);
                }
                $request->user->setMessage(__('The downloads configuration has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminDownloads',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $keys = array('labels_download_predefined', 'labels_download_one_max');
            foreach ($keys as $key) {
                $_val = $conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_UploadConf($params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/downloads.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Administrate the members of a project.
     */
    public $adminMembers_precond = array('IDF_Precondition::projectOwner');
    public function adminMembers($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Project Members'), (string) $prj);
        $params = array(
                        'project' => $prj,
                        'user' => $request->user,
                        );
        if ($request->method == 'POST') {
            $form = new IDF_Form_MembersConf($request->POST, $params);
            if ($form->isValid()) {
                $form->save();
                $request->user->setMessage(__('The project membership has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminMembers',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_MembersConf($prj->getMembershipData('string'), $params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/members.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Administrate the access rights to the tabs.
     */
    public $adminTabs_precond = array('IDF_Precondition::projectOwner');
    public function adminTabs($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Tabs Access Rights'), (string) $prj);
        $extra = array(
                       'project' => $prj,
                       'conf' => $request->conf,
                       );
        if ($request->method == 'POST') {
            $form = new IDF_Form_TabsConf($request->POST, $extra);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    $request->conf->setVal($key, $val);
                }
                $form->save(); // Save the authorized users.
                $request->user->setMessage(__('The project tabs access rights have been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminTabs',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $keys = array('downloads_access_rights', 'source_access_rights',
                          'issues_access_rights', 'private_project');
            foreach ($keys as $key) {
                $_val = $request->conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            // Add the authorized users.
            $md = $prj->getMembershipData('string');
            $params['authorized_users'] = $md['authorized'];
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_TabsConf($params, $extra);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/tabs.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Administrate the source control.
     */
    public $adminSource_precond = array('IDF_Precondition::projectOwner');
    public function adminSource($request, $match)
    {
        $prj = $request->project;
        $title = sprintf(__('%s Source'), (string) $prj);
        $extra = array(
                       'conf' => $request->conf,
                       );
        if ($request->method == 'POST') {
            $form = new IDF_Form_SourceConf($request->POST, $extra);
            if ($form->isValid()) {
                foreach ($form->cleaned_data as $key=>$val) {
                    $request->conf->setVal($key, $val);
                }
                $request->user->setMessage(__('The project source configuration  has been saved.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Project::adminSource',
                                                array($prj->shortname));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $params = array();
            $keys = array('scm', 'svn_remote_url', 
                          'svn_username', 'svn_password');
            foreach ($keys as $key) {
                $_val = $request->conf->getVal($key, false);
                if ($_val !== false) {
                    $params[$key] = $_val;
                }
            }
            if (count($params) == 0) {
                $params = null; //Nothing in the db, so new form.
            }
            $form = new IDF_Form_SourceConf($params, $extra);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/admin/source.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }
}