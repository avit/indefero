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
 * Update a documentation page.
 *
 * This add a corresponding revision.
 *
 */
class IDF_Form_WikiUpdate extends Pluf_Form
{
    public $user = null;
    public $project = null;
    public $page = null;
    public $show_full = false;
    

    public function initFields($extra=array())
    {
        $this->page = $extra['page'];
        $this->user = $extra['user'];
        $this->project = $extra['project'];
        if ($this->user->hasPerm('IDF.project-owner', $this->project)
            or $this->user->hasPerm('IDF.project-member', $this->project)) {
            $this->show_full = true;
        }
        if ($this->show_full) {
            $this->fields['title'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Page title'),
                                            'initial' => $this->page->title,
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            'help_text' => __('The page name must contains only letters, digits and the dash (-) character.'),
                                            ));
            $this->fields['summary'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Description'),
                                            'help_text' => __('This one line description is displayed in the list of pages.'),
                                            'initial' => $this->page->summary,
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));
        }
        $rev = $this->page->get_current_revision();
        $this->fields['content'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Content'),
                                            'initial' => $rev->content,
                                            'widget' => 'Pluf_Form_Widget_TextareaInput',
                                            'widget_attrs' => array(
                                                       'cols' => 68,
                                                       'rows' => 26,
                                                                    ),
                                            ));
        $this->fields['comment'] = new Pluf_Form_Field_Varchar(
                                      array('required' => true,
                                            'label' => __('Comment'),
                                            'help_text' => __('One line to describe the changes you made.'),
                                            'initial' => '',
                                            'widget_attrs' => array(
                                                       'maxlength' => 200,
                                                       'size' => 67,
                                                                    ),
                                            ));

        if ($this->show_full) {
            $tags = $this->page->get_tags_list();
            for ($i=1;$i<4;$i++) {
                $initial = '';
                if (isset($tags[$i-1])) {
                    if ($tags[$i-1]->class != 'Other') {
                        $initial = (string) $tags[$i-1];
                    } else {
                        $initial = $tags[$i-1]->name;
                    }
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

    public function clean_title()
    {
        $title = $this->cleaned_data['title'];
        if (preg_match('/[^a-zA-Z0-9\-]/', $title)) {
            throw new Pluf_Form_Invalid(__('The title contains invalid characters.'));
        }
        $sql = new Pluf_SQL('project=%s AND title=%s', 
                            array($this->project->id, $title));
        $pages = Pluf::factory('IDF_WikiPage')->getList(array('filter'=>$sql->gen()));
        if ($pages->count() > 0 and $pages[0]->id != $this->page->id) {
            throw new Pluf_Form_Invalid(__('A page with this title already exists.'));
        }
        return $title;
    }

    /**
     * Validate the interconnection in the form.
     */
    public function clean()
    {
        if (!$this->show_full) {
            return $this->cleaned_data;
        }
        $conf = new IDF_Conf();
        $conf->setProject($this->project);
        $onemax = array();
        foreach (explode(',', $conf->getVal('labels_wiki_one_max', IDF_Form_WikiConf::init_one_max)) as $class) {
            if (trim($class) != '') {
                $onemax[] = mb_strtolower(trim($class));
            }
        }
        $count = array();
        for ($i=1;$i<4;$i++) {
            $this->cleaned_data['label'.$i] = trim($this->cleaned_data['label'.$i]);
            if (strpos($this->cleaned_data['label'.$i], ':') !== false) {
                list($class, $name) = explode(':', $this->cleaned_data['label'.$i], 2);
                list($class, $name) = array(mb_strtolower(trim($class)), 
                                            trim($name));
            } else {
                $class = 'other';
                $name = $this->cleaned_data['label'.$i];
            }
            if (!isset($count[$class])) $count[$class] = 1;
            else $count[$class] += 1;
            if (in_array($class, $onemax) and $count[$class] > 1) {
                if (!isset($this->errors['label'.$i])) $this->errors['label'.$i] = array();
                $this->errors['label'.$i][] = sprintf(__('You cannot provide more than label from the %s class to a page.'), $class);
                throw new Pluf_Form_Invalid(__('You provided an invalid label.'));
            }
        }
        return $this->cleaned_data;
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
        if ($this->show_full) {
            $tagids = array();
            $tags = array();
            for ($i=1;$i<4;$i++) {
                if (strlen($this->cleaned_data['label'.$i]) > 0) {
                    if (strpos($this->cleaned_data['label'.$i], ':') !== false) {
                        list($class, $name) = explode(':', $this->cleaned_data['label'.$i], 2);
                        list($class, $name) = array(trim($class), trim($name));
                    } else {
                        $class = 'Other';
                        $name = trim($this->cleaned_data['label'.$i]);
                    }
                    $tag = IDF_Tag::add($name, $this->project, $class);
                    $tags[] = $tag;
                    $tagids[] = $tag->id;
                }
            }
            // Compare between the old and the new data
            $changes = array();
            $oldtags = $this->page->get_tags_list();
            foreach ($tags as $tag) {
                if (!Pluf_Model_InArray($tag, $oldtags)) {
                    if (!isset($changes['lb'])) $changes['lb'] = array();
                    if ($tag->class != 'Other') {
                        $changes['lb'][] = (string) $tag; //new tag
                    } else {
                        $changes['lb'][] = (string) $tag->name;
                    }
                }
            }
            foreach ($oldtags as $tag) {
                if (!Pluf_Model_InArray($tag, $tags)) {
                    if (!isset($changes['lb'])) $changes['lb'] = array();
                    if ($tag->class != 'Other') {
                        $changes['lb'][] = '-'.(string) $tag; //new tag
                    } else {
                        $changes['lb'][] = '-'.(string) $tag->name;
                    }
                }
            }
            if (trim($this->page->summary) != trim($this->cleaned_data['summary'])) {
                $changes['su'] = trim($this->cleaned_data['summary']);
            }
            // Update the page
            $this->page->batchAssoc('IDF_Tag', $tagids);
            $this->page->summary = trim($this->cleaned_data['summary']);
            $this->page->title = trim($this->cleaned_data['title']);
        } else {
            $changes = array();
        }
        $this->page->update();
        // add the new revision
        $rev = new IDF_WikiRevision();
        $rev->wikipage = $this->page;
        $rev->content = $this->cleaned_data['content'];
        $rev->submitter = $this->user;
        $rev->summary = $this->cleaned_data['comment'];
        $rev->changes = $changes;
        $rev->create();
        $rev->notify($this->project->getConf(), false);
        return $this->page;
    }
}
