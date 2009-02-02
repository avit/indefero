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
 * Diff parser.
 *
 */
class IDF_Diff
{
    public $repo = '';
    public $diff = '';
    protected $lines = array();

    public $files = array();

    public function __construct($diff, $repo='')
    {
        $this->repo = $repo;
        $this->diff = $diff;
        $this->lines = preg_split("/\015\012|\015|\012/", $diff);
    }

    public function parse()
    {
        $current_file = '';
        $current_chunk = 0;
        $lline = 0;
        $rline = 0;
        $files = array();
        $indiff = false; // Used to skip the headers in the git patches
        $i = 0; // Used to skip the end of a git patch with --\nversion number
        foreach ($this->lines as $line) {
            $i++;
            if (0 === strpos($line, '--') and isset($this->lines[$i]) 
                and preg_match('/^\d+\.\d+\.\d+\.\d+$/', $this->lines[$i])) {
                break;
            }
            if (0 === strpos($line, 'diff --git a')) {
                $current_file = self::getFile($line);
                $files[$current_file] = array();
                $files[$current_file]['chunks'] = array();
                $files[$current_file]['chunks_def'] = array();
                $current_chunk = 0;
                $indiff = true;
                continue;
            } else if (preg_match('#^diff -r [^\s]+ -r [^\s]+ (.+)$#', $line, $matches)) {
                $current_file = $matches[1];
                $files[$current_file] = array();
                $files[$current_file]['chunks'] = array();
                $files[$current_file]['chunks_def'] = array();
                $current_chunk = 0;
                $indiff = true;
                continue;
            } else if (0 === strpos($line, 'Index: ')) {
                $current_file = self::getSvnFile($line);
                $files[$current_file] = array();
                $files[$current_file]['chunks'] = array();
                $files[$current_file]['chunks_def'] = array();
                $current_chunk = 0;
                $indiff = true;
                continue;
            }
            if (!$indiff) {
                continue;
            }
            if (0 === strpos($line, '@@ ')) {
                $files[$current_file]['chunks_def'][] = self::getChunk($line);
                $files[$current_file]['chunks'][] = array();
                $current_chunk++;
                $lline = $files[$current_file]['chunks_def'][$current_chunk-1][0][0];
                $rline = $files[$current_file]['chunks_def'][$current_chunk-1][1][0];
                continue;
            }
            if (0 === strpos($line, '---') or 0 === strpos($line, '+++')) {
                continue;
            }
            if (0 === strpos($line, '-')) {
                $files[$current_file]['chunks'][$current_chunk-1][] = array($lline, '', substr($line, 1));
                $lline++;
                continue;
            }
            if (0 === strpos($line, '+')) {
                $files[$current_file]['chunks'][$current_chunk-1][] = array('', $rline, substr($line, 1));
                $rline++;
                continue;
            }
            if (0 === strpos($line, ' ')) {
                $files[$current_file]['chunks'][$current_chunk-1][] = array($lline, $rline, substr($line, 1));
                $rline++;
                $lline++;
                continue;
            }
            if ($line == '') {
                $files[$current_file]['chunks'][$current_chunk-1][] = array($lline, $rline, $line);
                $rline++;
                $lline++;
                continue;
            }
        }
        $this->files = $files;
        return $files;
    }

    public static function getFile($line)
    {
        $line = substr(trim($line), 10);
        $n = (int) strlen($line)/2;
        return trim(substr($line, 3, $n-3));
    }

    public static function getSvnFile($line)
    {
        return substr(trim($line), 7);
    }

    /**
     * Return the html version of a parsed diff.
     */
    public function as_html()
    {
        $out = '';
        foreach ($this->files as $filename=>$file) {
            $pretty = '';
            $fileinfo = IDF_Views_Source::getMimeType($filename);
            if (IDF_Views_Source::isSupportedExtension($fileinfo[2])) {
                $pretty = ' prettyprint';
            }
            $out .= "\n".'<table class="diff" summary="">'."\n";
            $out .= '<tr id="diff-'.md5($filename).'"><th colspan="3">'.Pluf_esc($filename).'</th></tr>'."\n";
            $cc = 1;
            foreach ($file['chunks'] as $chunk) {
                foreach ($chunk as $line) {
                    if ($line[0] and $line[1]) {
                        $class = 'diff-c';
                    } elseif ($line[0]) {
                        $class = 'diff-r';
                    } else {
                        $class = 'diff-a';
                    }
                    $line_content = self::padLine(Pluf_esc($line[2]));
                    $out .= sprintf('<tr class="diff-line"><td class="diff-lc">%s</td><td class="diff-lc">%s</td><td class="%s%s mono">%s</td></tr>'."\n", $line[0], $line[1], $class, $pretty, $line_content);
                }
                if (count($file['chunks']) > $cc)
                $out .= '<tr class="diff-next"><td>...</td><td>...</td><td>&nbsp;</td></tr>'."\n";
                $cc++;
            }
            $out .= '</table>';
        }
        return Pluf_Template::markSafe($out);
    }


    public static function padLine($line)
    {
        $n = strlen($line);
        for ($i=0;$i<$n;$i++) {
            if (substr($line, $i, 1) != ' ') {
                break;
            }
        }
        return str_repeat('&nbsp;', $i).substr($line, $i);
    }

    /**
     * @return array array(array(start, n), array(start, n))
     */
    public static function getChunk($line)
    {
        $elts = split(' ', $line);
        $res = array();
        for ($i=1;$i<3;$i++) {
            $res[] = split(',', trim(substr($elts[$i], 1)));
        }
        return $res;
    }

    /**
     * Review patch.
     *
     * Given the original file as a string and the parsed
     * corresponding diff chunks, generate a side by side view of the
     * original file and new file with added/removed lines.
     *
     * Example of use:
     *
     * $diff = new IDF_Diff(file_get_contents($diff_file));
     * $orig = file_get_contents($orig_file);
     * $diff->parse();
     * echo $diff->fileCompare($orig, $diff->files[$orig_file], $diff_file);
     *
     * @param string Original file
     * @param array Chunk description of the diff corresponding to the file
     * @param string Original file name
     * @param int Number of lines before/after the chunk to be displayed (10)
     * @return Pluf_Template_SafeString The table body
     */
    public function fileCompare($orig, $chunks, $filename, $context=10) 
    {
        $orig_lines = preg_split("/\015\012|\015|\012/", $orig);
        $new_chunks = $this->mergeChunks($orig_lines, $chunks, $context);
        return $this->renderCompared($new_chunks, $filename);
    }

    public function mergeChunks($orig_lines, $chunks, $context=10) 
    {
        $spans = array();
        $new_chunks = array();
        $min_line = 0;
        $max_line = 0;
        //if (count($chunks['chunks_def']) == 0) return '';
        foreach ($chunks['chunks_def'] as $chunk) {
            $start = ($chunk[0][0] > $context) ? $chunk[0][0]-$context : 0;
            $end = (($chunk[0][0]+$chunk[0][1]+$context-1) < count($orig_lines)) ? $chunk[0][0]+$chunk[0][1]+$context-1 : count($orig_lines);
            $spans[] = array($start, $end);
        }
        // merge chunks/get the chunk lines
        // these are reference lines
        $chunk_lines = array();
        foreach ($chunks['chunks'] as $chunk) {
            foreach ($chunk as $line) {
                $chunk_lines[] = $line;
            }
        }
        $i = 0;
        foreach ($chunks['chunks'] as $chunk) {
            $n_chunk = array();
            // add lines before
            if ($chunk[0][0] > $spans[$i][0]) {
                for ($lc=$spans[$i][0];$lc<$chunk[0][0];$lc++) {
                    $exists = false;
                    foreach ($chunk_lines as $line) {
                        if ($lc == $line[0] 
                            or ($chunk[0][1]-$chunk[0][0]+$lc) == $line[1]) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $n_chunk[] = array(
                                           $lc, 
                                           $chunk[0][1]-$chunk[0][0]+$lc,
                                           $orig_lines[$lc-1]
                                           );
                    }
                }
            }
            // add chunk lines
            foreach ($chunk as $line) {
                $n_chunk[] = $line;
            }
            // add lines after
            $lline = $line;
            if (!empty($lline[0]) and $lline[0] < $spans[$i][1]) {
                for ($lc=$lline[0];$lc<=$spans[$i][1];$lc++) {
                    $exists = false;
                    foreach ($chunk_lines as $line) {
                        if ($lc == $line[0] or ($lline[1]-$lline[0]+$lc) == $line[1]) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $n_chunk[] = array(
                                           $lc, 
                                           $lline[1]-$lline[0]+$lc,
                                           $orig_lines[$lc-1]
                                           );
                    }
                }
            }
            $new_chunks[] = $n_chunk;
            $i++;
        }
        // Now, each chunk has the right length, we need to merge them
        // when needed
        $nnew_chunks = array();
        $i = 0;
        foreach ($new_chunks as $chunk) {
            if ($i>0) {
                $lline = end($nnew_chunks[$i-1]);
                if ($chunk[0][0] <= $lline[0]+1) {
                    // need merging
                    foreach ($chunk as $line) {
                        if ($line[0] > $lline[0] or empty($line[0])) {
                            $nnew_chunks[$i-1][] = $line;
                        } 
                    }
                } else {
                    $nnew_chunks[] = $chunk;
                    $i++;
                }
            } else {
                $nnew_chunks[] = $chunk;
                $i++;
            }
        }
        return $nnew_chunks;
    }


    public function renderCompared($chunks, $filename)
    {
        $fileinfo = IDF_Views_Source::getMimeType($filename);
        $pretty = '';
        if (IDF_Views_Source::isSupportedExtension($fileinfo[2])) {
            $pretty = ' prettyprint';
        }
        $out = '';
        $cc = 1;
        $i = 0;
        foreach ($chunks as $chunk) {
            foreach ($chunk as $line) {
                $line1 = '&nbsp;';
                $line2 = '&nbsp;';
                $line[2] = (strlen($line[2])) ? self::padLine(Pluf_esc($line[2])) : '&nbsp;';
                if ($line[0] and $line[1]) {
                    $class = 'diff-c';
                    $line1 = $line2 = $line[2];
                } elseif ($line[0]) {
                    $class = 'diff-r';
                    $line1 = $line[2];
                } else {
                    $class = 'diff-a';
                    $line2 = $line[2];
                }
                $out .= sprintf('<tr class="diff-line"><td class="diff-lc">%s</td><td class="%s mono%s"><code>%s</code></td><td class="diff-lc">%s</td><td class="%s mono%s"><code>%s</code></td></tr>'."\n", $line[0], $class, $pretty, $line1, $line[1], $class, $pretty, $line2);
            }
            if (count($chunks) > $cc)
                $out .= '<tr class="diff-next"><td>...</td><td>&nbsp;</td><td>...</td><td>&nbsp;</td></tr>'."\n";
            $cc++;
            $i++;
        }
        return Pluf_Template::markSafe($out);

    }
}
