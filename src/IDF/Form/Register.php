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
 * Create a new user account.
 *
 */
class IDF_Form_Register extends Pluf_Form
{
    protected $request;

    public function initFields($extra=array())
    {
        $this->request = $extra['request'];
        $login = '';
        if (isset($extra['initial']['login'])) {
            $login = $extra['initial']['login'];
        }
        $this->fields['login'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Your login'),
                                            'max_length' => 15,
                                            'min_length' => 3,
                                            'initial' => $login,
                                            'help_text' => __('The login must be between 3 and 15 characters long and contains only letters and digits.'),
                                            'widget_attrs' => array(
                                                       'maxlength' => 15,
                                                       'size' => 10,
                                                                    ),
                                            ));
        $this->fields['email'] = new Pluf_Form_Field_Email(
                                      array('required' => true,
                                            'label' => __('Your email'),
                                            'initial' => '',
                                            'help_text' => __('We will never send you any unsolicited emails. We hate spams too!'),
                                            ));

        $this->fields['terms'] = new Pluf_Form_Field_Boolean(
                                      array('required' => true,
                                            'label' => __('I agree to the terms and conditions.'),
                                            'initial' => '',
                                            ));
    }

    /**
     * Validate the interconnection in the form.
     */
    public function clean_login()
    {
        $this->cleaned_data['login'] = mb_strtolower(trim($this->cleaned_data['login']));
        if (preg_match('/[^A-Za-z0-9]/', $this->cleaned_data['login'])) {
            throw new Pluf_Form_Invalid(sprintf(__('The login "%s" can only contain letters and digits.'), $this->cleaned_data['login']));
        }
        $guser = new Pluf_User();
        $sql = new Pluf_SQL('login=%s', $this->cleaned_data['login']);
        if ($guser->getCount(array('filter' => $sql->gen())) > 0) {
            throw new Pluf_Form_Invalid(sprintf(__('The login "%s" is already used, please find another one.'), $this->cleaned_data['login']));
        }
        return $this->cleaned_data['login'];
    }

    /**
     * Check the terms.
     */
    public function clean_terms()
    {
        if (!$this->cleaned_data['terms']) {
            throw new Pluf_Form_Invalid(__('We know, this is boring, but you need to agree with the terms and conditions.'));
        }
        return $this->cleaned_data['terms'];
    }

    function clean_email()
    {
        $this->cleaned_data['email'] = mb_strtolower(trim($this->cleaned_data['email']));
        $guser = new Pluf_User();
        $sql = new Pluf_SQL('email=%s', $this->cleaned_data['email']);
        if ($guser->getCount(array('filter' => $sql->gen())) > 0) {
            throw new Pluf_Form_Invalid(sprintf(__('The email "%s" is already used. If you need, click on the help link to recover your password.'), $this->cleaned_data['email']));
        }
        return $this->cleaned_data['email'];
    }

    /**
     * Save the model in the database.
     *
     * @param bool Commit in the database or not. If not, the object
     *             is returned but not saved in the database.
     * @return Object Model with data set from the form.
     */
    function save($commit=true)
    {
        if (!$this->isValid()) {
            throw new Exception(__('Cannot save the model from an invalid form.'));
        }
        $user = new Pluf_User();
        $user->first_name = '---'; // with both this set and
                                   // active==false we can find later
                                   // on, all the unconfirmed accounts
                                   // that could be purged.
        $user->last_name = $this->cleaned_data['login'];
        $user->login = $this->cleaned_data['login'];
        $user->email = $this->cleaned_data['email'];
        $user->language = $this->request->language_code;
        $user->active = false;
        $user->create();
        self::sendVerificationEmail($user);
        return $user;
    }

    public static function sendVerificationEmail($user)
    {
        Pluf::loadFunction('Pluf_HTTP_URL_urlForView');
        $from_email = Pluf::f('from_email');
        $cr = new Pluf_Crypt(md5(Pluf::f('secret_key')));
        $encrypted = trim($cr->encrypt($user->email.':'.$user->id), '~');
        $key = substr(md5(Pluf::f('secret_key').$encrypted), 0, 2).$encrypted;
        $url = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views::registerConfirmation', array($key), array(), false);
        $urlik = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views::registerInputKey', array(), array(), false);
        $context = new Pluf_Template_Context(
             array('key' => $key,
                   'url' => $url,
                   'urlik' => $urlik,
                   'user'=> $user,
                   )
                                             );
        $tmpl = new Pluf_Template('idf/register/confirmation-email.txt');
        $text_email = $tmpl->render($context);
        $email = new Pluf_Mail($from_email, $user->email,
                               __('Confirm the creation of your account.'));
        $email->addTextMessage($text_email);
        $email->sendMail();
    }
}
