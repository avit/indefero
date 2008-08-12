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

/**
 * User management views.
 *
 * Edit your account.
 * Add emails for the link between a commit and an account.
 */
class IDF_Views_User
{
    /**
     * Simple management of the base info of the user.
     */
    public $myAccount_precond = array('Pluf_Precondition::loginRequired');
    public function myAccount($request, $match)
    {
        $params = array('user' => $request->user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_UserAccount($request->POST, $params);
            if ($form->isValid()) {
                $user = $form->save();
                $url = Pluf_HTTP_URL_urlForView('IDF_Views::index');
                $request->user->setMessage(__('Your personal information have been updated.'));
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_UserAccount($request->user->getData(), $params);
        }
        return Pluf_Shortcuts_RenderToResponse('user/myaccount.html', 
                                               array('page_title' => __('Your Account'),
                                                     'form' => $form),
                                               $request);
    }

    /**
     * Public profile of a user.
     */
    public function view($request, $match)
    {
        $projects = Pluf::factory('IDF_Project')->getList(); 
        $sql = new Pluf_SQL('login=%s', array($match[1]));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen())); 
        if (count($users) != 1 or !$users[0]->active) {
            throw new Pluf_HTTP_Error404();
        }
        return Pluf_Shortcuts_RenderToResponse('user/public.html', 
                                               array('page_title' => (string) $users[0],
                                                     'member' => $users[0],
                                                     'projects' => $projects,
                                                     ),
                                               $request);
    }

}