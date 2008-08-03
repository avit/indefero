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
 * Make the links to issues and commits.
 */
class IDF_Template_IssueComment extends Pluf_Template_Tag
{
    private $project = null;
    private $git = null;

    function start($text, $project)
    {
        $this->project = $project;
        $this->git = new IDF_Git(Pluf::f('git_repository'));
        $text = wordwrap($text, 69, "\n", true);
        $text = Pluf_esc($text);
        $text = ereg_replace('[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]', 
                             '<a href="\\0" rel="nofollow">\\0</a>', 
                             $text); 
        $text = preg_replace_callback('#(issues?|bugs?|tickets?)\s+(\d+)((\s+and|\s+or|,)\s+(\d+)){0,}#im',
                                      array($this, 'callbackIssues'), $text);
        $text = preg_replace_callback('#(commit\s+)([0-9a-f]{5,40})#im',
                                      array($this, 'callbackCommit'), $text);
        echo $text;
    }

    /**
     * General call back for the issues.
     */
    function callbackIssues($m)
    {
        if (count($m) == 3) {
            $issue = new IDF_Issue($m[2]);
            if ($issue->id > 0 and $issue->project == $this->project->id) {
                return $this->linkIssue($issue, $m[1].' '.$m[2]);
            } else {
                return $m[0]; // not existing issue.
            }
        } else {
            return preg_replace_callback('/(\d+)/', 
                                         array($this, 'callbackIssue'), 
                                         $m[0]); 
        }
    }

    /**
     * Call back for the case of multiple issues like 'issues 1, 2 and 3'.
     *
     * Called from callbackIssues, it is linking only the number of
     * the issues.
     */
    function callbackIssue($m)
    {
        $issue = new IDF_Issue($m[1]);
        if ($issue->id > 0 and $issue->project == $this->project->id) {
            return $this->linkIssue($issue, $m[1]);
        } else {
            return $m[0]; // not existing issue.
        }
    }

    function callbackCommit($m)
    {
        if ($this->git->testHash($m[2]) != 'commit') {
            return $m[0];
        }
        $co = $this->git->getCommit($m[2]);
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Source::commit', array($this->project->shortname, $co->commit)).'">'.$m[1].$m[2].'</a>';
    }

    /**
     * Generate the link to an issue.
     *
     * @param IDF_Issue Issue.
     * @param string Name of the link.
     * @return string Linked issue.
     */
    public function linkIssue($issue, $title)
    {
        $ic = (in_array($issue->status, $this->project->getTagIdsByStatus('closed'))) ? 'issue-c' : 'issue-o';
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view', 
                                                    array($this->project->shortname, $issue->id)).'" class="'.$ic.'" title="'.Pluf_esc($issue->summary).'">'.Pluf_esc($title).'</a>';
    }
}
