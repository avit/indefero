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
 * View git repository.
 */
class IDF_Views_Source
{
    public function changeLog($request, $match)
    {
        $title = sprintf('%s Git Change Log', (string) $request->project);
        $git = new IDF_Git(Pluf::f('git_repository'));
        $branches = $git->getBranches();
        $commit = $match[2];
        $res = $git->getChangeLog($commit, 50);
        return Pluf_Shortcuts_RenderToResponse('source/changelog.html',
                                               array(
                                                     'page_title' => $title,
                                                     'title' => $title,
                                                     'changes' => $res,
                                                     'commit' => $commit,
                                                     'branches' => $branches,
                                                     ),
                                               $request);
    }

    public function treeBase($request, $match)
    {
        $title = sprintf('%s Git Source Tree', (string) $request->project);
        $git = new IDF_Git(Pluf::f('git_repository'));
        $commit = $match[2];
        $branches = $git->getBranches();
        if ('commit' != $git->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $res = $git->filesAtCommit($commit);
        $cobject = $git->getCommit($commit);
        $tree_in = in_array($commit, $branches);
        return Pluf_Shortcuts_RenderToResponse('source/tree.html',
                                               array(
                                                     'page_title' => $title,
                                                     'title' => $title,
                                                     'files' => $res,
                                                     'cobject' => $cobject,
                                                     'commit' => $commit,
                                                     'tree_in' => $tree_in,
                                                     'branches' => $branches,
                                                     ),
                                               $request);
    }

    public function tree($request, $match)
    {
        $title = sprintf('%s Git Source Tree', (string) $request->project);
        $git = new IDF_Git(Pluf::f('git_repository'));
        $branches = $git->getBranches();
        $commit = $match[2];
        if ('commit' != $git->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $request_file = $match[3];
        $request_file_info = $git->getFileInfo($request_file, $commit);
        if (!$request_file_info) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        if ($request_file_info->type != 'tree') {
            return new Pluf_HTTP_Response($git->getBlob($request_file_info->hash),
                                          'application/octet-stream');
        }
        $bc = self::makeBreadCrumb($request->project, $commit, $request_file_info->file);
        $page_title = $bc.' - '.$title;
        $cobject = $git->getCommit($commit);
        $tree_in = in_array($commit, $branches);
        $res = $git->filesAtCommit($commit, $request_file);
        // try to find the previous level if it exists.
        $prev = split('/', $request_file);
        $l = array_pop($prev);
        $previous = substr($request_file, 0, -strlen($l.' '));
        return Pluf_Shortcuts_RenderToResponse('source/tree.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'breadcrumb' => $bc,
                                                     'files' => $res,
                                                     'commit' => $commit,
                                                     'cobject' => $cobject,
                                                     'base' => $request_file_info->file,
                                                     'prev' => $previous,
                                                     'tree_in' => $tree_in,
                                                     'branches' => $branches,
                                                     ),
                                               $request);
    }

    public static function makeBreadCrumb($project, $commit, $file, $sep='/')
    {
        $elts = split('/', $file);
        $out = array();
        $stack = '';
        $i = 0;
        foreach ($elts as $elt) {
            $stack .= ($i==0) ? $elt : '/'.$elt;
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::tree',
                                            array($project->shortname,
                                                  $commit, $stack));
            $out[] = '<a href="'.$url.'">'.Pluf_esc($elt).'</a>';
            $i++;
        }
        return '<span class="breadcrumb">'.implode('<span class="sep">'.$sep.'</span>', $out).'</span>';
    }

    public function commit($request, $match)
    {

        $git = new IDF_Git(Pluf::f('git_repository'));
        $commit = $match[2];
        $branches = $git->getBranches();
        if ('commit' != $git->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $title = sprintf('%s Commit Details', (string) $request->project);
        $page_title = sprintf('%s Commit Details - %s', (string) $request->project, $commit);
        $cobject = $git->getCommit($commit);
        require_once 'Text/Highlighter.php';
        $th = new Text_Highlighter();
        $h = $th->factory('DIFF');
        $changes = $h->highlight($cobject->changes);
        return Pluf_Shortcuts_RenderToResponse('source/commit.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'changes' => $changes,
                                                     'cobject' => $cobject,
                                                     'commit' => $commit,
                                                     'branches' => $branches,
                                                     ),
                                               $request);
    }


}

function IDF_Views_Source_PrettySize($size)
{
    return Pluf_Template::markSafe(Pluf_Utils::prettySize($size));
}

