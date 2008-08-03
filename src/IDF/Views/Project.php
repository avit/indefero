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
    public function home($request, $match)
    {
        $prj = $request->project;
        $team = $prj->getMembershipData();
        $title = (string) $prj;
        return Pluf_Shortcuts_RenderToResponse('project-home.html',
                                               array(
                                                     'page_title' => $title,
                                                     'team' => $team,
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
        return Pluf_Shortcuts_RenderToResponse('admin/summary.html',
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
        return Pluf_Shortcuts_RenderToResponse('admin/issue-tracking.html',
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
        return Pluf_Shortcuts_RenderToResponse('admin/downloads.html',
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
        return Pluf_Shortcuts_RenderToResponse('admin/members.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }
}