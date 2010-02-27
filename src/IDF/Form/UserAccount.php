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

/**
 * Allow a user to update its details.
 */
class IDF_Form_UserAccount  extends Pluf_Form
{
    public $user = null;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $this->fields['first_name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('First name'),
                                            'initial' => $this->user->first_name,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        $this->fields['last_name'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Last name'),
                                            'initial' => $this->user->last_name,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 20,
                                                                    ),
                                            ));

        $this->fields['email'] = new Pluf_Form_Field_Email(
                                      array('required' => true,
                                            'label' => __('Your mail'),
                                            'initial' => $this->user->email,
                                            'help_text' => __('If you change your email address, an email will be sent to the new address to confirm it.'),
                                            ));

        $this->fields['language'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Language'),
                                            'initial' => $this->user->language,
                                            'widget' => 'Pluf_Form_Widget_SelectInput',
                                            'widget_attrs' => array(
                                                       'choices' => 
                                                       Pluf_L10n::getInstalledLanguages()
                                                                    ),
                                            ));

        $this->fields['password'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Your password'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_PasswordInput',
                                            'help_text' => Pluf_Template::markSafe(__('Leave blank if you do not want to change your password.').'<br />'.__('Your password must be hard for other people to find it, but easy for you to remember.')),
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));
        $this->fields['password2'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Confirm your password'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_PasswordInput',
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 15,
                                                                    ),
                                            ));

        $this->fields['ssh_key'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Add a public SSH key'),
                                            'initial' => '',
                                            'widget_attrs' => array('rows' => 3,
                                                                    'cols' => 40),
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'help_text' => __('Be careful to provide your public key and not your private key!')
                                            ));
        

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
        unset($this->cleaned_data['password2']);
        $update_pass = false;
        if (strlen($this->cleaned_data['password']) == 0) {
            unset($this->cleaned_data['password']);
        } else {
            $update_pass = true;
        }
        $old_email = $this->user->email;
        $new_email = $this->cleaned_data['email'];
        unset($this->cleaned_data['email']);
        if ($old_email != $new_email) {
            $cr = new Pluf_Crypt(md5(Pluf::f('secret_key')));
            $encrypted = trim($cr->encrypt($new_email.':'.$this->user->id.':'.time()), '~');
            $key = substr(md5(Pluf::f('secret_key').$encrypted), 0, 2).$encrypted;
            $url = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views_User::changeEmailDo', array($key), array(), false);
            $urlik = Pluf::f('url_base').Pluf_HTTP_URL_urlForView('IDF_Views_User::changeEmailInputKey', array(), array(), false);
            $context = new Pluf_Template_Context(
                 array('key' => Pluf_Template::markSafe($key),
                       'url' => Pluf_Template::markSafe($url),
                       'urlik' => Pluf_Template::markSafe($urlik),
                       'email' => $new_email,
                       'user'=> $this->user,
                       )
                                                 );
            $tmpl = new Pluf_Template('idf/user/changeemail-email.txt');
            $text_email = $tmpl->render($context);
            $email = new Pluf_Mail(Pluf::f('from_email'), $new_email,
                                   __('Confirm your new email address.'));
            $email->addTextMessage($text_email);
            $email->sendMail();
            $this->user->setMessage(sprintf(__('A validation email has been sent to "%s" to validate the email address change.'), Pluf_esc($new_email)));
        }
        $this->user->setFromFormData($this->cleaned_data);
        // Add key as needed.
        if ('' !== $this->cleaned_data['ssh_key']) {
            $key = new IDF_Key();
            $key->user = $this->user;
            $key->content = $this->cleaned_data['ssh_key'];
            if ($commit) {
                $key->create();
            }
        }
        if ($commit) {
            $this->user->update();
            if ($update_pass) {
                /**
                 * [signal]
                 *
                 * Pluf_User::passwordUpdated
                 *
                 * [sender]
                 *
                 * IDF_Form_UserAccount
                 *
                 * [description]
                 *
                 * This signal is sent when the user updated his
                 * password from his account page.
                 *
                 * [parameters]
                 *
                 * array('user' => $user)
                 *
                 */
                $params = array('user' => $this->user);
                Pluf_Signal::send('Pluf_User::passwordUpdated',
                                  'IDF_Form_UserAccount', $params);
            }
        }
        return $this->user;
    }

    /**
     * Check an ssh key.
     *
     * It will throw a Pluf_Form_Invalid exception if it cannot
     * validate the key.
     *
     * @param $key string The key
     * @param $user int The user id of the user of the key (0)
     * @return string The clean key
     */
    public static function checkSshKey($key, $user=0)
    {
        $key = trim($key);
        if (strlen($key) == 0) {
            return '';
        }
        $key = str_replace(array("\n", "\r"), '', $key);
        if (!preg_match('#^ssh\-[a-z]{3}\s(\S+)\s\S+$#', $key, $matches)) {
            throw new Pluf_Form_Invalid(__('The format of the key is not valid. It must start with ssh-dss or ssh-rsa, a long string on a single line and at the end a comment.'));
        }
        if (Pluf::f('idf_strong_key_check', false)) {
            $tmpfile = Pluf::f('tmp_folder', '/tmp').$user.'-key';
            file_put_contents($tmpfile, $key, LOCK_EX);
            $cmd = Pluf::f('idf_exec_cmd_prefix', '').
                'ssh-keygen -l -f '.escapeshellarg($tmpfile);
            exec($cmd, $out, $return);
            unlink($tmpfile);
            if ($return != 0) {
                throw new Pluf_Form_Invalid(__('Please check the key as it does not appears to be a valid key.'));
            }
        }
        return $key;
    }

    function clean_ssh_key()
    {
        return self::checkSshKey($this->cleaned_data['ssh_key'], 
                                 $this->user->id);
    }

    function clean_last_name()
    {
        $last_name = trim($this->cleaned_data['last_name']);
        if ($last_name == mb_strtoupper($last_name)) {
            return mb_convert_case(mb_strtolower($last_name), 
                                   MB_CASE_TITLE, 'UTF-8');
        }
        return $last_name;
    }

    function clean_first_name()
    {
        $first_name = trim($this->cleaned_data['first_name']);
        if ($first_name == mb_strtoupper($first_name)) {
            return mb_convert_case(mb_strtolower($first_name), 
                                   MB_CASE_TITLE, 'UTF-8');
        }
        return $first_name;
    }

    function clean_email()
    {
        $this->cleaned_data['email'] = mb_strtolower(trim($this->cleaned_data['email']));
        $guser = new Pluf_User();
        $sql = new Pluf_SQL('email=%s AND id!=%s', 
                            array($this->cleaned_data['email'], $this->user->id));
        if ($guser->getCount(array('filter' => $sql->gen())) > 0) {
            throw new Pluf_Form_Invalid(sprintf(__('The email "%s" is already used.'), $this->cleaned_data['email']));
        }
        return $this->cleaned_data['email'];
    }

    /**
     * Check to see if the 2 passwords are the same.
     */
    public function clean()
    {
        if (!isset($this->errors['password']) 
            && !isset($this->errors['password2'])) {
            $password1 = $this->cleaned_data['password'];
            $password2 = $this->cleaned_data['password2'];
            if ($password1 != $password2) {
                throw new Pluf_Form_Invalid(__('The passwords do not match. Please give them again.'));
            }
        }
        return $this->cleaned_data;
    }
}
