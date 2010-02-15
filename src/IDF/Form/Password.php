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
 * Ask a password recovery.
 *
 */
class IDF_Form_Password extends Pluf_Form
{
    public function initFields($extra=array())
    {
        $this->fields['account'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Your login or email'),
                                            'help_text' => __('Provide either your login or your email to recover your password.'),
                                            ));
    }

    /**
     * Validate that a user with this login or email exists.
     */
    public function clean_account()
    {
        $account = mb_strtolower(trim($this->cleaned_data['account']));
        $sql = new Pluf_SQL('email=%s OR login=%s',
                            array($account, $account));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));
        if ($users->count() == 0) {
            throw new Pluf_Form_Invalid(__('Sorry, we cannot find a user with this email address or login. Feel free to try again.'));
        }
        $ok = false;
        foreach ($users as $user) {
            if ($user->active) {
                $ok = true;
                continue;
            }
            if (!$user->active and $user->first_name == '---') {
                $ok = true;
                continue;
            }
            $ok = false; // This ensures an all or nothing ok.
        }
        if (!$ok) {
            throw new Pluf_Form_Invalid(__('Sorry, we cannot find a user with this email address or login. Feel free to try again.'));
        }
        return $account;
    }

    /**
     * Send the reminder email.
     *
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        $account = $this->cleaned_data['account'];
        $sql = new Pluf_SQL('email=%s OR login=%s',
                            array($account, $account));
        $users = Pluf::factory('Pluf_User')->getList(array('filter'=>$sql->gen()));

        $return_url = '';
        foreach ($users as $user) {
            if ($user->active) {
                $return_url = Pluf_HTTP_URL_urlForView('IDF_Views::passwordRecoveryInputCode');
                $tmpl = new Pluf_Template('idf/user/passrecovery-email.txt');
                $cr = new Pluf_Crypt(md5(Pluf::f('secret_key')));
                $code = trim($cr->encrypt($user->email.':'.$user->id.':'.time()), 
                             '~');
                $code = substr(md5(Pluf::f('secret_key').$code), 0, 2).$code;
                $url = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views::passwordRecovery', array($code), array(), false);
                $urlic = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views::passwordRecoveryInputCode', array(), array(), false);
                $context = new Pluf_Template_Context(
                         array('url' => Pluf_Template::markSafe($url),
                               'urlik' => Pluf_Template::markSafe($urlic),
                               'user' => Pluf_Template::markSafe($user),
                               'key' => Pluf_Template::markSafe($code)));
                $email = new Pluf_Mail(Pluf::f('from_email'), $user->email,
                                       __('Password Recovery - InDefero'));
                $email->setReturnPath(Pluf::f('bounce_email', Pluf::f('from_email')));
                $email->addTextMessage($tmpl->render($context));
                $email->sendMail();
            }
            if (!$user->active and $user->first_name == '---') {
                $return_url = Pluf_HTTP_URL_urlForView('IDF_Views::registerInputKey');
                IDF_Form_Register::sendVerificationEmail($user);
            }
        }
        return $return_url;
    }
}
