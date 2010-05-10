<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008, 2009, 2010 Céondo Ltd and contributors.
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
 * Management of the webhooks.
 *
 * The class provides the tools to perform the POST request with
 * authentication for the webhooks.
 *
 */
class IDF_Webhook
{
    /**
     * Perform the POST request given the webhook payload.
     *
     * @param array Payload
     * @return bool Success or error
     */
    public static function postNotification($payload)
    {
        $data = json_encode($payload['to_send']);
        $sign = hash_hmac('md5', $data, $payload['authkey']);
        $params = array('http' => array(
                      'method' => 'POST',
                      'content' => $data,
                      'user_agent' => 'Indefero Hook Sender (http://www.indefero.net)',
                      'max_redirects' => 0, 
                      'timeout' => 15,
                      'header'=> 'Post-Commit-Hook-Hmac: '.$sign."\r\n"
                                .'Content-Type: application/json'."\r\n",
                                        )
                        );
        $url = $payload['url'];
        $ctx = stream_context_create($params);
        $fp = @fopen($url, 'rb', false, $ctx);
        if (!$fp) {
            return false;
        }
        $meta = stream_get_meta_data($fp);
        @fclose($fp);
        if (!isset($meta['wrapper_data'][0]) or $meta['timed_out']) {
            return false;
        }
        if (0 === strpos($meta['wrapper_data'][0], 'HTTP/1.1 2') or 
            0 === strpos($meta['wrapper_data'][0], 'HTTP/1.1 3')) {
            return true;
        }
        return false;
    }


    /**
     * Process the webhook.
     *
     */
    public static function process($sender, &$params)
    {
        $item = $params['item'];
        if ($item->type != 'new_commit') {
            // We do nothing.
            return;
        }
        if (isset($params['res']['IDF_Webhook::process']) and 
            $params['res']['IDF_Webhook::process'] == true) {
            // Already processed.
            return;
        }
        if ($item->payload['url'] == '') {
            // We do nothing.
            return;
        }
        // We have either to retry or to push for the first time.
        $res = self::postNotification($item->payload);
        if ($res) {
            $params['res']['IDF_Webhook::process'] = true;
        } elseif ($item->trials >= 9) {
            // We are at trial 10, give up
            $params['res']['IDF_Webhook::process'] = true;
        } else {
            // Need to try again
            $params['res']['IDF_Webhook::process'] = false;
        }
    }
}
