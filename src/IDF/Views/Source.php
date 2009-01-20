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
    public static $supportedExtenstions = array('c', 'cc', 'cpp', 'cs', 'css', 
                                                'cyc', 'java', 'bsh', 'csh', 
                                                'sh', 'cv', 'py', 'perl', 'php',
                                                'pl', 'pm', 'rb', 'js', 'html',
                                                'html', 'xhtml', 'xml', 'xsl');

    public $changeLog_precond = array('IDF_Precondition::accessSource');
    public function changeLog($request, $match)
    {
        $title = sprintf(__('%1$s %2$s Change Log'), (string) $request->project,
                         $this->getScmType($request));
        $scm = IDF_Scm::get($request->project);
        $branches = $scm->getBranches();
        $commit = $match[2];
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::changeLog',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $changes = $scm->getChangeLog($commit, 25);
        $rchanges = array();
        // Sync with the database
        foreach ($changes as $change) {
            $rchanges[] = IDF_Commit::getOrAdd($change, $request->project);
        }
        $rchanges = new Pluf_Template_ContextVars($rchanges);
        $scmConf = $request->conf->getVal('scm', 'git');
        return Pluf_Shortcuts_RenderToResponse('idf/source/changelog.html',
                                               array(
                                                     'page_title' => $title,
                                                     'title' => $title,
                                                     'changes' => $rchanges,
                                                     'commit' => $commit,
                                                     'branches' => $branches,
                                                     'scm' => $scmConf,
                                                     ),
                                               $request);
    }

    public $treeBase_precond = array('IDF_Precondition::accessSource');
    public function treeBase($request, $match)
    {
        $title = sprintf(__('%1$s %2$s Source Tree'), (string) $request->project,
                         $this->getScmType($request));
        $scm = IDF_Scm::get($request->project);
        $commit = $match[2];
        $branches = $scm->getBranches();
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $cache = Pluf_Cache::factory();
        $key = 'IDF_Views_Source::treeBase:'.$commit.'::';
        if (null === ($res=$cache->get($key))) {
            $res = new Pluf_Template_ContextVars($scm->filesAtCommit($commit));
            $cache->set($key, $res);
        }
        $cobject = $scm->getCommit($commit);
        $tree_in = in_array($commit, $branches);
        $scmConf = $request->conf->getVal('scm', 'git');
        $props = null;
        if ($scmConf === 'svn') {
            $props = $scm->getProperties($commit);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/tree.html',
                                               array(
                                                     'page_title' => $title,
                                                     'title' => $title,
                                                     'files' => $res,
                                                     'cobject' => $cobject,
                                                     'commit' => $commit,
                                                     'tree_in' => $tree_in,
                                                     'branches' => $branches,
                                                     'props' => $props,
                                                     ),
                                               $request);
    }

    public $tree_precond = array('IDF_Precondition::accessSource');
    public function tree($request, $match)
    {
        $title = sprintf(__('%1$s %2$s Source Tree'), (string) $request->project,
                         $this->getScmType($request));
        $scm = IDF_Scm::get($request->project);
        $branches = $scm->getBranches();
        $commit = $match[2];
        $request_file = $match[3];
        $fburl = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                          array($request->project->shortname,
                                                $branches[0]));
        if ('commit' != $scm->testHash($commit, $request_file)) {
            // Redirect to the first branch
            return new Pluf_HTTP_Response_Redirect($fburl);
        }
        $request_file_info = $scm->getFileInfo($request_file, $commit);
        if (!$request_file_info) {
            // Redirect to the first branch
            return new Pluf_HTTP_Response_Redirect($fburl);
        }
        if ($request_file_info->type != 'tree') {
            $info = self::getMimeType($request_file_info->file);
            if (!self::isText($info)) {
                $rep = new Pluf_HTTP_Response($scm->getBlob($request_file_info, $commit),
                                              $info[0]);
                $rep->headers['Content-Disposition'] = 'attachment; filename="'.$info[1].'"';
                return $rep;
            } else {
                // We want to display the content of the file as text
                $extra = array('branches' => $branches,
                               'commit' => $commit,
                               'request_file' => $request_file,
                               'request_file_info' => $request_file_info,
                               'mime' => $info,
                               );
                return $this->viewFile($request, $match, $extra);
            }
        }
        $bc = self::makeBreadCrumb($request->project, $commit, $request_file_info->file);
        $page_title = $bc.' - '.$title;
        $cobject = $scm->getCommit($commit);
        $tree_in = in_array($commit, $branches);
        try {
            $cache = Pluf_Cache::factory();
            $key = 'IDF_Views_Source::tree:'.$commit.'::'.$request_file;
            if (null === ($res=$cache->get($key))) {
                $res = new Pluf_Template_ContextVars($scm->filesAtCommit($commit, $request_file));
                $cache->set($key, $res);
            }
        } catch (Exception $e) {
            return new Pluf_HTTP_Response_Redirect($fburl);
        }
        // try to find the previous level if it exists.
        $prev = split('/', $request_file);
        $l = array_pop($prev);
        $previous = substr($request_file, 0, -strlen($l.' '));
        $scmConf = $request->conf->getVal('scm', 'git');
        $props = null;
        if ($scmConf === 'svn') {
            $props = $scm->getProperties($commit, $request_file);
        }
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/tree.html',
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
                                                     'props' => $props,
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

    public $commit_precond = array('IDF_Precondition::accessSource');
    public function commit($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $commit = $match[2];
        $branches = $scm->getBranches();
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $title = sprintf(__('%s Commit Details'), (string) $request->project);
        $page_title = sprintf(__('%s Commit Details - %s'), (string) $request->project, $commit);
        $large = $scm->isCommitLarge($commit);
        $cobject = $scm->getCommit($commit, !$large);
        $rcommit = IDF_Commit::getOrAdd($cobject, $request->project);
        $diff = new IDF_Diff($cobject->changes);
        $diff->parse();
        $scmConf = $request->conf->getVal('scm', 'git');
        return Pluf_Shortcuts_RenderToResponse('idf/source/commit.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'diff' => $diff,
                                                     'cobject' => $cobject,
                                                     'commit' => $commit,
                                                     'branches' => $branches,
                                                     'scm' => $scmConf,
                                                     'rcommit' => $rcommit,
                                                     'large_commit' => $large,
                                                     ),
                                               $request);
    }

    public $downloadDiff_precond = array('IDF_Precondition::accessSource');
    public function downloadDiff($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $commit = $match[2];
        $branches = $scm->getBranches();
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $cobject = $scm->getCommit($commit);
        $rep = new Pluf_HTTP_Response($cobject->changes, 'text/plain');
        $rep->headers['Content-Disposition'] = 'attachment; filename="'.$commit.'.diff"';
        return $rep;
    }

    /**
     * Should only be called through self::tree
     */
    public function viewFile($request, $match, $extra)
    {
        $title = sprintf(__('%1$s %2$s Source Tree'), (string) $request->project,
                         $this->getScmType($request));
        $scm = IDF_Scm::get($request->project);
        $branches = $extra['branches'];
        $commit = $extra['commit'];
        $request_file = $extra['request_file'];
        $request_file_info = $extra['request_file_info'];
        $bc = self::makeBreadCrumb($request->project, $commit, $request_file_info->file);
        $page_title = $bc.' - '.$title;
        $cobject = $scm->getCommit($commit);
        $tree_in = in_array($commit, $branches);
        // try to find the previous level if it exists.
        $prev = split('/', $request_file);
        $l = array_pop($prev);
        $previous = substr($request_file, 0, -strlen($l.' '));
        $scmConf = $request->conf->getVal('scm', 'git');
        $props = null;
        if ($scmConf === 'svn') {
            $props = $scm->getProperties($commit, $request_file);
        }
        $content = self::highLight($extra['mime'], $scm->getBlob($request_file_info, $commit));
        return Pluf_Shortcuts_RenderToResponse('idf/source/'.$scmConf.'/file.html',
                                               array(
                                                     'page_title' => $page_title,
                                                     'title' => $title,
                                                     'breadcrumb' => $bc,
                                                     'file' => $content,
                                                     'commit' => $commit,
                                                     'cobject' => $cobject,
                                                     'fullpath' => $request_file,
                                                     'base' => $request_file_info->file,
                                                     'prev' => $previous,
                                                     'tree_in' => $tree_in,
                                                     'branches' => $branches,
                                                     'props' => $props,
                                                     ),
                                               $request);
    }

    /**
     * Get a given file at a given commit.
     *
     */
    public $getFile_precond = array('IDF_Precondition::accessSource');
    public function getFile($request, $match)
    {
        $scm = IDF_Scm::get($request->project);
        $branches = $scm->getBranches();
        $commit = $match[2];
        $request_file = $match[3];
        if ('commit' != $scm->testHash($commit, $request_file)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $request_file_info = $scm->getFileInfo($request_file, $commit);
        if (!$request_file_info or $request_file_info->type == 'tree') {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $info = self::getMimeType($request_file_info->file);
        $rep = new Pluf_HTTP_Response($scm->getBlob($request_file_info, $commit),
                                      $info[0]);
        $rep->headers['Content-Disposition'] = 'attachment; filename="'.$info[1].'"';
        return $rep;
    }

    /**
     * Get a zip archive of the current commit.
     *
     */
    public $download_precond = array('IDF_Precondition::accessSource');
    public function download($request, $match)
    {
        $commit = trim($match[2]);
        $scm = IDF_Scm::get($request->project);
        $branches = $scm->getBranches();
        if ('commit' != $scm->testHash($commit)) {
            // Redirect to the first branch
            $url = Pluf_HTTP_URL_urlForView('IDF_Views_Source::treeBase',
                                            array($request->project->shortname,
                                                  $branches[0]));
            return new Pluf_HTTP_Response_Redirect($url);
        }
        $base = $request->project->shortname.'-'.$commit;
        $cmd = $scm->getArchiveCommand($commit, $base.'/');
        $rep = new Pluf_HTTP_Response_CommandPassThru($cmd, 'application/x-zip');
        $rep->headers['Content-Transfer-Encoding'] = 'binary';
        $rep->headers['Content-Disposition'] = 'attachment; filename="'.$base.'.zip"';
        return $rep;
    }

    /**
     * Find the mime type of a file.
     *
     * Use /etc/mime.types to find the type.
     *
     * @param string Filename/Filepath
     * @param array  Mime type found or 'application/octet-stream', basename, extension
     */
    public static function getMimeType($file)
    {
        $src= Pluf::f('idf_mimetypes_db', '/etc/mime.types');
        $mimes = preg_split("/\015\012|\015|\012/", file_get_contents($src));
        $info = pathinfo($file);
        if (isset($info['extension'])) {
            foreach ($mimes as $mime) {
                if ('#' != substr($mime, 0, 1)) {
                    $elts = preg_split('/ |\t/', $mime, -1, PREG_SPLIT_NO_EMPTY);
                    if (in_array($info['extension'], $elts)) {
                        return array($elts[0], $info['basename'], $info['extension']);
                    }
                }
            }
        } else {
            // we consider that if no extension and base name is all
            // uppercase, then we have a text file.
            if ($info['basename'] == strtoupper($info['basename'])) {
                return array('text/plain', $info['basename'], 'txt');
            }
            $info['extension'] = 'bin';
        }
        return array('application/octet-stream', $info['basename'], $info['extension']);
    }

    /**
     * Find if a given mime type is a text file.
     * This uses the output of the self::getMimeType function.
     *
     * @param array (Mime type, file name, extension)
     * @return bool Is text
     */
    public static function isText($fileinfo)
    {
        if (0 === strpos($fileinfo[0], 'text/')) {
            return true;
        }
        $ext = 'mdtext php js cpp php-dist h gitignore sh py pl rb diff patch'
            .Pluf::f('idf_extra_text_ext', '');
        return (in_array($fileinfo[2], explode(' ', $ext)));
    }

    public static function highLight($fileinfo, $content)
    {
        $pretty = '';
        if (IDF_Views_Source::isSupportedExtension($fileinfo[2])) {
            $pretty = ' prettyprint';
        }
        $table = array();
        $i = 1;
        foreach (preg_split("/\015\012|\015|\012/", $content) as $line) {
            $table[] = '<tr class="c-line"><td class="code-lc" id="L'.$i.'"><a href="#L'.$i.'">'.$i.'</a></td>'
                .'<td class="code mono'.$pretty.'">'.IDF_Diff::padLine(Pluf_esc($line)).'</td></tr>';
            $i++;
        }
        return Pluf_Template::markSafe(implode("\n", $table));
    }

    /**
     * @param string the extension to test
     * 
     * @return 
     */
    public static function isSupportedExtension($extension)
    {
        return in_array($extension, IDF_Views_Source::$supportedExtenstions);
    }

    /**
     * Get the scm type for page title
     *
     * @return String
     */
    private function getScmType($request)
    {
        return mb_convert_case($request->conf->getVal('scm', 'git'),
                               MB_CASE_TITLE, 'UTF-8');
    }
}

function IDF_Views_Source_PrettySize($size)
{
    return Pluf_Template::markSafe(str_replace(' ', '&nbsp;',
                                               Pluf_Utils::prettySize($size)));
}

function IDF_Views_Source_PrettySizeSimple($size)
{
    return Pluf_Utils::prettySize($size);
}

