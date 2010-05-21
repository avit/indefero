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
 * Create a new issue.
 *
 * This create the issue entry and the first comment corresponding to
 * the description and the attached files.
 *
 * It is possible to tag the issue following some rules. For example
 * you cannot put several "status" or "priority" tags.
 *
 */
class IDF_Form_IssueCreate extends Pluf_Form
{
    public $user = null;
    public $project = null;
    public $show_full = false;

    public function initFields($extra=array())
    {
        $this->user = $extra['user'];
        $this->project = $extra['project'];
        if ($this->user->hasPerm('IDF.project-owner', $this->project)
            or $this->user->hasPerm('IDF.project-member', $this->project)) {
            $this->show_full = true;
        }
        $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Summary'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));
        $this->fields['content'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Description'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 58,
                                                       'rows' => 13,
                                                                    ),
                                            ));
        $upload_path = Pluf::f('upload_issue_path', false);
        if (false === $upload_path) {
            throw new Pluf_Exception_SettingError(__('The "upload_issue_path" configuration variable was not set.'));
        }
        $md5 = md5(rand().microtime().Pluf_Utils::getRandomString());
        // We add .dummy to try to mitigate security issues in the
        // case of someone allowing the upload path to be accessible
        // to everybody.
        for ($i=1;$i<4;$i++) {
            $filename = substr($md5, 0, 2).'/'.substr($md5, 2, 2).'/'.substr($md5, 4).'/%s.dummy'; 
            $this->fields['attachment'.$i] = new Pluf_Form_Field_File(
                array('required' => false,
                      'label' => __('Attach a file'),
                      'move_function_params' => 
                      array('upload_path' => $upload_path,
                            'upload_path_create' => true,
                            'file_name' => $filename,
                            )
                      )
                );
        }

        if ($this->show_full) {
            $this->fields['status'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Status'),
                                            'initial' => 'New',
                                            'widget_attrs' => array(
                                                       'maxlength' => 20,
                                                       'size' => 15,
                                                                    ),
                                            ));
            $this->fields['owner'] = new Pluf_Form_Field_Varchar(
                                      array('required' => false,
                                            'label' => __('Owner'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 20,
                                                       'size' => 15,
                                                                    ),
                                            ));
            for ($i=1;$i<7;$i++) {
                $initial = '';
                switch ($i) {
                case 1:
                    $initial = 'Type:Defect';
                    break;
                case 2:
                    $initial = 'Priority:Medium';
                    break;
                }
                $this->fields['label'.$i] = new Pluf_Form_Field_Varchar(
                                            array('required' => false,
                                                  'label' => __('Labels'),
                                            'initial' => $initial,
                                            'widget_attrs' => array(
                                                       'maxlength' => 50,
                                                       'size' => 20,
                                                                    ),
                                                  ));
            }
        }
    }

    /**
     * Validate the interconnection in the form.
     */
    public function clean()
    {
        // We need to check that no label with the 'Status' class is
        // given.
        if (!$this->show_full) {
            return $this->cleaned_data;
        }
        $conf = new IDF_Conf();
        $conf->setProject($this->project);
        $onemax = array();
        foreach (explode(',', $conf->getVal('labels_issue_one_max', IDF_Form_IssueTrackingConf::init_one_max)) as $class) {
            if (trim($class) != '') {
                $onemax[] = mb_strtolower(trim($class));
            }
        }
        $count = array();
        for ($i=1;$i<7;$i++) {
            $this->cleaned_data['label'.$i] = trim($this->cleaned_data['label'.$i]);
            if (strpos($this->cleaned_data['label'.$i], ':') !== false) {
                list($class, $name) = explode(':', $this->cleaned_data['label'.$i], 2);
                list($class, $name) = array(mb_strtolower(trim($class)), 
                                            trim($name));
            } else {
                $class = 'other';
                $name = $this->cleaned_data['label'.$i];
            }
            if ($class == 'status') {
                if (!isset($this->errors['label'.$i])) $this->errors['label'.$i] = array();
                $this->errors['label'.$i][] = __('You cannot add a label with the "Status" prefix to an issue.');
                throw new Pluf_Form_Invalid(__('You provided an invalid label.'));
            }
            if (!isset($count[$class])) $count[$class] = 1;
            else $count[$class] += 1;
            if (in_array($class, $onemax) and $count[$class] > 1) {
                if (!isset($this->errors['label'.$i])) $this->errors['label'.$i] = array();
                $this->errors['label'.$i][] = sprintf(__('You cannot provide more than label from the %s class to an issue.'), $class);
                throw new Pluf_Form_Invalid(__('You provided an invalid label.'));
            }
        }
        return $this->cleaned_data;
    }

    function clean_content()
    {
        $content = trim($this->cleaned_data['content']);
        if (strlen($content) == 0) {
            throw new Pluf_Form_Invalid(__('You need to provide a description of the issue.'));
        }
        return $content;
    }

    function clean_status()
    {
        // Check that the status is in the list of official status
        $tags = $this->project->getTagsFromConfig('labels_issue_open', 
                                          IDF_Form_IssueTrackingConf::init_open,
                                          'Status');
        $tags = array_merge($this->project->getTagsFromConfig('labels_issue_closed', 
                                          IDF_Form_IssueTrackingConf::init_closed,
                                          'Status')
                            , $tags);
        $found = false;
        foreach ($tags as $tag) {
            if ($tag->name == trim($this->cleaned_data['status'])) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Pluf_Form_Invalid(__('You provided an invalid status.'));
        }
        return $this->cleaned_data['status'];
    }

    /**
     * Clean the attachments post failure.
     */
    function failed()
    {
        $upload_path = Pluf::f('upload_issue_path', false);
        if ($upload_path == false) return;
        for ($i=1;$i<4;$i++) {
            if (!empty($this->cleaned_data['attachment'.$i]) and
                file_exists($upload_path.'/'.$this->cleaned_data['attachment'.$i])) {
                @unlink($upload_path.'/'.$this->cleaned_data['attachment'.$i]);
            }
        }
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
        // Add a tag for each label
        $tags = array();
        if ($this->show_full) {
            for ($i=1;$i<7;$i++) {
                if (strlen($this->cleaned_data['label'.$i]) > 0) {
                    if (strpos($this->cleaned_data['label'.$i], ':') !== false) {
                        list($class, $name) = explode(':', $this->cleaned_data['label'.$i], 2);
                        list($class, $name) = array(trim($class), trim($name));
                    } else {
                        $class = 'Other';
                        $name = trim($this->cleaned_data['label'.$i]);
                    }
                    $tags[] = IDF_Tag::add($name, $this->project, $class);
                }
            }
        } else {
            $tags[] = IDF_Tag::add('Medium', $this->project, 'Priority');
            $tags[] = IDF_Tag::add('Defect', $this->project, 'Type');
        }
        // Create the issue
        $issue = new IDF_Issue();
        $issue->project = $this->project;
        $issue->submitter = $this->user;
        if ($this->show_full) {
            $issue->status = IDF_Tag::add(trim($this->cleaned_data['status']), $this->project, 'Status');
            $issue->owner = self::findUser($this->cleaned_data['owner']);
        } else {
            $_t = $this->project->getTagIdsByStatus('open');
            $issue->status = new IDF_Tag($_t[0]); // first one is the default
            $issue->owner = null;
        }
        $issue->summary = trim($this->cleaned_data['summary']);
        $issue->create();
        foreach ($tags as $tag) {
            $issue->setAssoc($tag);
        }
        // add the first comment
        $comment = new IDF_IssueComment();
        $comment->issue = $issue;
        $comment->content = $this->cleaned_data['content'];
        $comment->submitter = $this->user;
        $comment->create();
        // If we have a file, create the IDF_IssueFile and attach
        // it to the comment.
        $created_files = array();
        for ($i=1;$i<4;$i++) {
            if ($this->cleaned_data['attachment'.$i]) {
                $file = new IDF_IssueFile();
                $file->attachment = $this->cleaned_data['attachment'.$i];
                $file->submitter = $this->user;
                $file->comment = $comment;
                $file->create();
                $created_files[] = $file;
            }
        }
        /**
         * [signal]
         *
         * IDF_Issue::create
         *
         * [sender]
         *
         * IDF_Form_IssueCreate
         *
         * [description]
         *
         * This signal allows an application to perform a set of tasks
         * just after the creation of an issue. The comment contains
         * the description of the issue.
         *
         * [parameters]
         *
         * array('issue' => $issue,
         *       'comment' => $comment,
         *       'files' => $attached_files);
         *
         */
        $params = array('issue' => $issue,
                        'comment' => $comment,
                        'files' => $created_files);
        Pluf_Signal::send('IDF_Issue::create', 'IDF_Form_IssueCreate',
                          $params);
        return $issue;
    }

    /**
     * Based on the given string, try to find the matching user.
     *
     * Search order is: email, login, last_name.
     *
     * If no user found, simply returns null.
     *
     * @param string User
     * @return Pluf_User or null
     */
    public static function findUser($string)
    {
        $string = trim($string);
        if (strlen($string) == 0) return null;
        $guser = new Pluf_User();
        foreach (array('email', 'login', 'last_name') as $what) {
            $sql = new Pluf_SQL($what.'=%s', $string);
            $users = $guser->getList(array('filter' => $sql->gen()));
            if ($users->count() > 0) {
                return $users[0];
            }
        }
        return null;
    }
}
