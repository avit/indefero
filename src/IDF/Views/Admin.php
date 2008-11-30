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
 * Administration's views.
 */
class IDF_Views_Admin
{
    /**
     * Home page of the administration.
     *
     * It should provide an overview of the forge status.
     */
    public $home_precond = array('Pluf_Precondition::staffRequired');
    public function home($request, $match)
    {
        $title = __('Administer');
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/home.html',
                                               array(
                                                     'page_title' => $title,
                                                     ),
                                               $request);
    }

    /**
     * Projects overview.
     *
     */
    public $projects_precond = array('Pluf_Precondition::staffRequired');
    public function projects($request, $match)
    {
        $title = __('Projects');
        $pag = new Pluf_Paginator(new IDF_Project());
        $pag->class = 'recent-issues';
        $pag->summary = __('This table shows the projects in the forge.');
        $pag->action = 'IDF_Views_Admin::projects';
        $pag->edit_action = array('IDF_Views_Admin::projectUpdate', 'id');
        $pag->sort_order = array('shortname', 'ASC');
        $list_display = array(
             'shortname' => __('Short Name'),
             'name' => __('Name'),
                              );
        $pag->configure($list_display, array(), 
                        array('shortname'));
        $pag->items_per_page = 25;
        $pag->no_results_text = __('No projects were found.');
        $pag->setFromRequest($request);
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/projects/index.html',
                                               array(
                                                     'page_title' => $title,
                                                     'projects' => $pag,
                                                     ),
                                               $request);
    }

    /**
     * Edition of a project.
     *
     * One cannot switch from one source backend to another.
     */
    public $projectUpdate_precond = array('Pluf_Precondition::staffRequired');
    public function projectUpdate($request, $match)
    {
        $project = Pluf_Shortcuts_GetObjectOr404('IDF_Project', $match[1]);
        $title = sprintf(__('Update %s'), $project->name);
        $params = array(
                        'project' => $project,
                        );
        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_ProjectUpdate($request->POST, $params);
            if ($form->isValid()) {
                $form->save();
                $request->user->setMessage(__('The project has been updated.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::projectUpdate',
                                                array($project->id));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Admin_ProjectUpdate(null, $params);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/projects/update.html',
                                               array(
                                                     'page_title' => $title,
                                                     'project' => $project,
                                                     'form' => $form,
                                                     ),
                                               $request);
    }

    /**
     * Creation of a project.
     *
     */
    public $projectCreate_precond = array('Pluf_Precondition::staffRequired');
    public function projectCreate($request, $match)
    {
        $title = __('Create Project');
        $extra = array('user' => $request->user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_Admin_ProjectCreate($request->POST, $extra);
            if ($form->isValid()) {
                $project = $form->save();
                $request->user->setMessage(__('The project has been created.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views_Admin::projects');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_Admin_ProjectCreate(null, $extra);
        }
        $base = Pluf::f('url_base').Pluf::f('idf_base').'/p/';
        return Pluf_Shortcuts_RenderToResponse('idf/gadmin/projects/create.html',
                                               array(
                                                     'page_title' => $title,
                                                     'form' => $form,
                                                     'base_url' => $base,
                                                     ),
                                               $request);
    }

}