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
 * Base views of InDefero.
 */
class IDF_Views
{
    /**
     * List all the projects managed by InDefero.
     */
    public function index($request, $match)
    {
        $projects = Pluf::factory('IDF_Project')->getList(); 
        return Pluf_Shortcuts_RenderToResponse('index.html', 
                                               array('page_title' => __('Projects'),
                                                     'projects' => $projects),
                                               $request);
    }

    /**
     * Login view.
     */
    public function login($request, $match)
    {
        $v = new Pluf_Views();
        return $v->login($request, $match, Pluf::f('login_success_url'));
    }

    /**
     * Logout view.
     */
    function logout($request, $match)
    {
        $views = new Pluf_Views();
        return $views->logout($request, $match, Pluf::f('after_logout_page'));
    }

}