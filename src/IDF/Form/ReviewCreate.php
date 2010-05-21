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
 * Create a new code review.
 *
 * This creates an IDF_Review and the corresponding IDF_Review_Patch.
 */
class IDF_Form_ReviewCreate extends Pluf_Form
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
        $this->fields['description'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Description'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 58,
                                                       'rows' => 7,
                                                                    ),
                                            ));
        $sql = new Pluf_SQL('project=%s', array($this->project->id));
        $commits = Pluf::factory('IDF_Commit')->getList(array('order' => 'creation_dtime DESC',
                                                              'nb' => 10,
                                                              'filter' => $sql->gen()));
        $choices = array();
        foreach ($commits as $c) {
            $id = (strlen($c->scm_id) > 10) ? substr($c->scm_id, 0, 10) : $c->scm_id;
            $ext = (mb_strlen($c->summary) > 50) ? mb_substr($c->summary, 0, 47).'...' : $c->summary;
            $choices[$id.' - '.$ext] = $c->scm_id;
        }
        $this->fields['commit'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Commit'),
                                            'initial' => '',
                                            'widget' => 'Pluf_Form_Widget_SelectInput',
                                            'widget_attrs' => array(
                                                       'choices' => $choices,
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
        $filename = substr($md5, 0, 2).'/'.substr($md5, 2, 2).'/'.substr($md5, 4).'/%s.dummy'; 
        $this->fields['patch'] = new Pluf_Form_Field_File(
                array('required' => true,
                      'label' => __('Patch'),
                      'move_function_params' => 
                      array('upload_path' => $upload_path,
                            'upload_path_create' => true,
                            'file_name' => $filename,
                            )
                      )
                );
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
        }
    }

    public function clean_patch()
    {
        $diff = new IDF_Diff(file_get_contents(Pluf::f('upload_issue_path').'/'
                                               .$this->cleaned_data['patch']));
        $diff->parse();
        if (count($diff->files) == 0) {
            throw new Pluf_Form_Invalid(__('We were not able to parse your patch. Please provide a valid patch.'));
        }
        return $this->cleaned_data['patch'];
    }

    public function clean_commit()
    {
        $commit = self::findCommit($this->cleaned_data['commit']);
        if (null == $commit) {
            throw new Pluf_Form_Invalid(__('You provided an invalid commit.'));
        }
        return $this->cleaned_data['commit'];
    }

    /**
     * Validate the interconnection in the form.
     */
    public function clean()
    {
        return $this->cleaned_data;
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
        if (!empty($this->cleaned_data['patch']) and
            file_exists($upload_path.'/'.$this->cleaned_data['patch'])) {
                @unlink($upload_path.'/'.$this->cleaned_data['patch']);
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
        // Create the review
        $review = new IDF_Review();
        $review->project = $this->project;
        $review->summary = $this->cleaned_data['summary'];
        $review->submitter = $this->user;
        if (!isset($this->cleaned_data['status'])) {
            $this->cleaned_data['status'] = 'New';
        }
        $review->status = IDF_Tag::add(trim($this->cleaned_data['status']), $this->project, 'Status');
        $review->create();
        // add the first patch
        $patch = new IDF_Review_Patch();
        $patch->review = $review;
        $patch->summary = __('Initial patch to be reviewed.');
        $patch->description = $this->cleaned_data['description'];
        $patch->commit = self::findCommit($this->cleaned_data['commit']);
        $patch->patch = $this->cleaned_data['patch'];
        $patch->create();
        $patch->notify($this->project->getConf());
        /**
         * [signal]
         *
         * IDF_Review::create
         *
         * [sender]
         *
         * IDF_Form_ReviewCreate
         *
         * [description]
         *
         * This signal allows an application to perform a set of tasks
         * just after the creation of a review and the notification.
         *
         * [parameters]
         *
         * array('review' => $review,
         *       'patch' => $patch);
         *
         */
        $params = array('review' => $review,
                        'patch' => $patch);
        Pluf_Signal::send('IDF_Review::create', 'IDF_Form_ReviewCreate',
                          $params);
        return $review;
    }

    /**
     * Based on the given string, try to find the matching commit.
     *
     * If no user found, simply returns null.
     *
     * @param string Commit
     * @return IDF_Commit or null
     */
    public static function findCommit($string)
    {
        $string = trim($string);
        if (strlen($string) == 0) return null;
        $gc = new IDF_Commit();
        $sql = new Pluf_SQL('scm_id=%s', array($string));
        $gcs = $gc->getList(array('filter' => $sql->gen()));
        if ($gcs->count() > 0) {
            return $gcs[0];
        }
        return null;
    }
}
