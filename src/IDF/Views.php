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
        if (isset($request->POST['action']) 
            and $request->POST['action'] == 'new-user') {
            $login = (isset($request->POST['login'])) ? $request->POST['login'] : '';
            $url = Pluf_HTTP_URL_urlForView('IDF_Views::register', array(),
                                            array('login' => $login));
            return new Pluf_HTTP_Response_Redirect($url);
        }
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

    /**
     * Registration.
     *
     * We just ask for login, email and to agree with the terms. Then,
     * we go ahead and send a confirmation email. The confirmation
     * email will allow to set the password, first name and last name
     * of the user.
     */
    function register($request, $match)
    {
        $title = __('Create Your Account');
        if ($request->method == 'POST') {
            $form = new IDF_Form_Register($request->POST);
            if ($form->isValid()) {
                $user = $form->save(); // It is sending the confirmation email
                $url = Pluf_HTTP_URL_urlForView('IDF_Views::registerInputKey');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $init = (isset($request->GET['login'])) ? array('initial' => array('login' => $request->GET['login'])) : array();
            $form = new IDF_Form_Register(null, $init);
        }
        return Pluf_Shortcuts_RenderToResponse('register/index.html', 
                                               array('page_title' => $title,
                                                     'form' => $form),
                                               $request);
    }

    /**
     * Input the registration confirmation key.
     *
     * Very simple view just to redirect to the register confirmation
     * views to input the password.
     */
    function registerInputKey($request, $match)
    {
        $title = __('Confirm Your Account Creation');
        if ($request->method == 'POST') {
            $form = new IDF_Form_RegisterInputKey($request->POST);
            if ($form->isValid()) {
                $url = $form->save();
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_RegisterInputKey();
        }
        return Pluf_Shortcuts_RenderToResponse('register/inputkey.html', 
                                               array('page_title' => $title,
                                                     'form' => $form),
                                               $request);
    }

    /**
     * Registration confirmation.
     *
     * Input first/last name, password and sign in the user.
     *
     * Maybe in the future send the user to its personal page for
     * customization.
     */
    function registerConfirmation($request, $match)
    {
        $title = __('Confirm Your Account Creation');
        $key = $match[1];
        // first "check", full check is done in the form.
        $email_id = IDF_Form_RegisterInputKey::checkKeyHash($key);
        if (false == $email_id) {
            $url = Pluf_HTTP_URL_urlForView('IDF_Views::registerInputKey');
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $user = new Pluf_User($email_id[1]);
        $extra = array('key' => $key,
                       'user' => $user);
        if ($request->method == 'POST') {
            $form = new IDF_Form_RegisterConfirmation($request->POST, $extra);
            if ($form->isValid()) {
                $user = $form->save();
                $request->user = $user;
                $request->session->clear();
                $request->session->setData('login_time', gmdate('Y-m-d H:i:s'));
                $user->last_login = gmdate('Y-m-d H:i:s');
                $user->update();                
                $request->user->setMessage(__('Welcome! You can now participate in the life of your project of choice.'));
                $url = Pluf_HTTP_URL_urlForView('IDF_Views::index');
                return new Pluf_HTTP_Response_Redirect($url);
            }
        } else {
            $form = new IDF_Form_RegisterConfirmation(null, $extra);
        }
        return Pluf_Shortcuts_RenderToResponse('register/confirmation.html', 
                                               array('page_title' => $title,
                                                     'new_user' => $user,
                                                     'form' => $form),
                                               $request);
    }

    /**
     * FAQ.
     */
    public function faq($request, $match)
    {
        $title = __('Here to Help You!');
        return Pluf_Shortcuts_RenderToResponse('faq.html', 
                                               array(
                                                     'page_title' => $title,
                                                     ),
                                               $request);

    }
}