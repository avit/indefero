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
    private $request = null;
    private $scm = null;

    function start($text, $request, $echo=true, $wordwrap=true, $esc=true, $autolink=true, $nl2br=false)
    {
        $this->project = $request->project;
        $this->request = $request;
        $this->scm = IDF_Scm::get($request->project);
        if ($wordwrap) $text = wordwrap($text, 69, "\n", true);
        if ($esc) $text = Pluf_esc($text);
        if ($autolink) {
            $text = preg_replace('#([a-z]+://[^\s\(\)]+)#i',
                                 '<a href="\1">\1</a>', $text);
        }
        if ($request->rights['hasIssuesAccess']) {
            $text = preg_replace_callback('#(issues?|bugs?|tickets?)\s+(\d+)(\#ic\d*){0,1}((\s+and|\s+or|,)\s+(\d+)(\#ic\d*){0,1}){0,}#im',
                                          array($this, 'callbackIssues'), $text);
        }
        if ($request->rights['hasSourceAccess']) {
            $text = preg_replace_callback('#(commits?\s+)([0-9a-f]{1,40}(?:(?:\s+and|\s+or|,)\s+[0-9a-f]{1,40})*)\b#i',
                                          array($this, 'callbackCommits'), $text);
            $text = preg_replace_callback('#(src:)([^\s\(\)]+)#im',
                                          array($this, 'callbackSource'), $text);
        }
        if ($nl2br) $text = nl2br($text);
        if ($echo) {
            echo $text;
        } else {
            return $text;
        }
    }

    /**
     * General call back for the issues.
     */
    function callbackIssues($m)
    {
        if (count($m) == 3 || count($m) == 4) {
            $issue = new IDF_Issue($m[2]);
            if ($issue->id > 0 and $issue->project == $this->project->id) {
                if (count($m) == 3) {
                	return $this->linkIssue($issue, $m[1].' '.$m[2]);
                } else {
                    return $this->linkIssue($issue, $m[1].' '.$m[2], $m[3]);
                }
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

     /**
      * General call back to convert commits to HTML links.
      *
      * @param array $m Single regex match.
      * @return string Content with converted commits.
      */
    function callbackCommits($m)
    {
        $keyword = rtrim($m[1]);
        if ('commits' === $keyword) {
            // Multiple commits like 'commits 6e030e6, a25bfc1 and
            // 3c094f8'.
            return $m[1].preg_replace_callback('#\b[0-9a-f]{4,40}\b#i', array($this, 'callbackCommit'), $m[2]);
        } else if ('commit' === $keyword) {
            // Single commit like 'commit 6e030e6'.
            return $m[1].call_user_func(array($this, 'callbackCommit'), array($m[2]));
        }
        return $m[0];
    }

    /**
     * Convert plaintext commit to HTML link. Called from callbackCommits.
     *
     * Regex callback for {@link IDF_Template_IssueComment::callbackCommits()}.
     *
     * @param array Single regex match.
     * @return string HTML A element with commit.
     */
    function callbackCommit($m)
    {
        $co = $this->scm->getCommit($m[0]);
        if (!$co) {
            return $m[0]; // not a commit.
        }
        return '<a href="'
            .Pluf_HTTP_URL_urlForView('IDF_Views_Source::commit', array($this->project->shortname, $co->commit))
            .'">'.$m[0].'</a>';
    }

    function callbackSource($m)
    {
        if (!$this->scm->isAvailable()) return $m[0];
        $file = $m[2];
        $request_file_info = $this->scm->getPathInfo($file);
        if (!$request_file_info) {
            return $m[0];
        }
        if ($request_file_info->type != 'tree') {
            return $m[1].'<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Source::tree', array($this->project->shortname, $this->scm->getMainBranch(), $file)).'">'.$m[2].'</a>';
        }
        return $m[0];
    }

    /**
     * Generate the link to an issue.
     *
     * @param IDF_Issue Issue.
     * @param string Name of the link.
     * @return string Linked issue.
     */
    public function linkIssue($issue, $title, $anchor='')
    {
        $ic = (in_array($issue->status, $this->project->getTagIdsByStatus('closed'))) ? 'issue-c' : 'issue-o';
        return '<a href="'.Pluf_HTTP_URL_urlForView('IDF_Views_Issue::view', 
                                                    array($this->project->shortname, $issue->id)).$anchor.'" class="'.$ic.'" title="'.Pluf_esc($issue->summary).'">'.Pluf_esc($title).'</a>';
    }
}
