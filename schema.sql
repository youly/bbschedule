CREATE TABLE IF NOT EXISTS `job_def` (
  `id` int(11) NOT NULL auto_increment COMMENT '自增ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '任务名称',
  `type` tinyint(4) unsigned NOT NULL DEFAULT '0' COMMENT '任务类型：0crontab固定间隔任务 1持续运行任务',
  `priority` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '任务优先级',
  `command` varchar(1024) NOT NULL COMMENT '要执行的指令',
  `env` varchar(1024) NOT NULL DEFAULT '' COMMENT  '环境变量',
  `concurrent` int(11) NOT NULL DEFAULT '1' COMMENT '并发个数',
  `maxtime` int(11) NOT NULL DEFAULT '0' COMMENT '最长运行时间',
  `period` varchar(64) NOT NULL DEFAULT '' COMMENT  '调度间隔',
  `group` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '所属组',
  `gmt_create` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `gmt_modified` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

insert into `job_def` (name,command,period) values ('test', '/usr/bin/uptime', '2/1 * * * * *');

CREATE TABLE IF NOT EXISTS `job_schedule` (
  `id` int(11) NOT NULL auto_increment COMMENT '自增ID',
  `job_id` int(11) unsigned NOT NULL COMMENT '任务id',
  `worker` varchar(32) NOT NULL DEFAULT '' COMMENT '执行机器',
  `gmt_start` int(11) unsigned NOT NULL COMMENT '开始时间',
  `gmt_end` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '开始时间',
  `is_running` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否正在运行',
  `pid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '进程id',
  `memory` varchar(32) default '' COMMENT '占用内存',
  `exit_code` int(11)  NOT NULL DEFAULT '1000' COMMENT '退出状态码',
  `result` text COMMENT '执行结果',
  `gmt_create` int(11) unsigned NOT NULL COMMENT '创建时间',
   KEY `idx_job_id` (`job_id`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `job_log` (
  `id` int(11) NOT NULL auto_increment COMMENT '自增ID',
  `schedule_id` int(11) unsigned NOT NULL COMMENT '任务执行id',
  `job_id` int(11) unsigned NOT NULL COMMENT '任务id',
  `pid` int(11) unsigned NOT NULL COMMENT '进程id',
  `log` text COMMENT '日志',
  `gmt_create` int(11) unsigned NOT NULL COMMENT '添加时间',
   KEY `idx_job_id` (`job_id`),
   KEY `idx_schedule_id` (`schedule_id`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `job_msg_queue` (
  `id` bigint(20) NOT NULL auto_increment COMMENT '自增ID',
  `body` blob COMMENT '消息内容',
  `priority` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '消息优先级',
  `gmt_create` int(11) unsigned NOT NULL COMMENT '添加时间',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
