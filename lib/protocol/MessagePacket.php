<?php namespace bbschedule\lib\protocol;

use \bbschedule\lib\protocol\Message;

/**
 * 通讯消息报文格式
 * 4字节大端报文长度 + 4字节header长度 + header数据 + body数据
 */
class MessagePacket {

    public static $eof = "\r\n";
    public static $eof_len = 2;

    const ERROR_PACKET_NOT_COMPLETE = 1;
    const ERROR_PACKET_FORMAT_ERROR = 2;
    const ERROR_PACKET_LEN_ERROR = 3;

    public static function encode(Message $msg) {
        $header = [
            'type' => $msg->type,
            'priority' => $msg->priority,
        ];
        $header = pack('A*', serialize($header));
        $header_len = strlen($header);
        $header_len_str = pack('N', $header_len);

        $body = pack('A*', serialize($msg->data));
        $body_len = strlen($body);

        $packet_len = 8 + $header_len + $body_len + self::$eof_len;
        $packet_len_str = pack('N', $packet_len);
        
        $buffer = new \bbschedule\lib\buffer\Buffer();
        $buffer->init(65536);
        $buffer->append($packet_len_str);
        $buffer->append($header_len_str);
        $buffer->append($header);
        $buffer->append($body);
        $buffer->append(self::$eof);
        $data = $buffer->get();
        $buffer->destroy();
        return $data;
    }

    public static function is_packet_complete($data) {
        if (substr($data, -2) !== self::$eof) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public static function decode($data, &$msg = NULL) {
        if (substr($data, -2) !== self::$eof) {
            return self::ERROR_PACKET_NOT_COMPLETE;
        }
        
        $packet_len = unpack('N', substr($data, 0, 4));
        if (empty($packet_len) || !isset($packet_len[1])) {
            return self::ERROR_PACKET_FORMAT_ERROR;
        }
        $packet_len = $packet_len[1];
        $data = substr($data, 4);

        $header_len = unpack('N', substr($data, 0, 4));
        if (empty($header_len) || !isset($header_len[1])) {
            echo "header len not exist\n";
            return self::ERROR_PACKET_FORMAT_ERROR;
        }
        $header_len = $header_len[1];
        $data = substr($data, 4);

        $header = unpack('A*', substr($data, 0, $header_len));
        $header = $header[1];
        if (strlen($header) != $header_len) {
            return self::ERROR_PACKET_FORMAT_ERROR;
        }
        $body = unpack('A*', substr($data, $header_len, -self::$eof_len));
        $body = $body[1];
        $body_len = strlen($body);
        if ((8 + $header_len + $body_len + self::$eof_len) != $packet_len) {
            return self::ERROR_PACKET_LEN_ERROR;
        }
        $header = unserialize($header);
        if (!is_array($header)) {
            return self::ERROR_PACKET_FORMAT_ERROR;
        }
        $msg = new Message();
        $msg->type = $header['type'];
        $msg->priority = $header['priority'];
        $msg->data = unserialize($body);
        return FALSE;
    }
}
