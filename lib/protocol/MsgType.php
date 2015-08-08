<?php namespace bbschedule\lib\protocol;

class MsgType {
    const TYPE_DISCONNECT = 0;
    const TYPE_LOAD_REPORT = 1;
    const TYPE_LOG = 2;
    const TYPE_JOB_DELIVERY = 3;
    const TYPE_JOB_FEEDBACK = 4;
}
