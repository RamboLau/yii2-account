
drop table if exists `user_account`;
create table `user_account` (
    `uid` bigint unsigned primary key comment '用户id',  
    `type` tinyint unsigned not null default 1 comment '帐户类别: 1.普通用户账号 2.公司账号 3.银行账号', 
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `is_enabled` tinyint(1) unsigned not null default 0 comment '账号状态: 1 有效 2 异常封禁 0为非法值',
    `balance` decimal(16,2) not null default 0.00 comment '用户账户现金余额', 
    `deposit` decimal(16,2) not null default 0.00 comment '保证金', 
    `frozon_money` decimal(16,2) not null default 0.00 comment '冻结资金', 
    `pay_password` varchar(64) not null default '' comment '支付密码',
    `last_balance_changed_ip` char(15) not null default '' comment '上次帐户余额更新ip',
    `last_balance_changed_at` char(12) not null default '' comment '上次帐户结余变更时间',
    `created_at` int(10) unsigned not null default 0 comment '账户开启时间', 
    `updated_at` int(10) not null default 0 comment '账户更新时间',
    key uidx_uid_actype (`uid`, `type`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户现金账户表';

drop table if exists `user_account_log`;
create table `user_account_log` (
    `id` bigint unsigned primary key auto_increment comment '自增账户日志id -- 流水id',  
    `uid` int unsigned not null comment '用户id',  
    `account_type` smallint unsigned not null default 1 comment '帐户类型:', 
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `trans_id` bigint unsigned not null comment '和变动关联的交易单号',
    `balance` decimal(16,2) not null default 0.00 comment '用户账户剩余资金', 
    `deposit` decimal(16,2) not null default 0.00 comment '用户冻结资金', 
    `balance_type` tinyint(1) unsigned not null default 1 comment '变动方向 1 账户金额增加 2 账户余额减少 其他非法',
    `tans_money` decimal(16,2) not null default 0.00 comment '变动金额',
    `tans_desc` varchar(64) not null default '' comment '变动描述',
    `created_at` int(10) unsigned not null default 0 comment '资金流动行为产生时间',
    key uidx_uid_actype (`uid`, `account_type`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户账户历史表';


drop table if exists `trans_type`;
create table `trans_type` (
    `id` smallint unsigned primary key not null auto_increment comment '自增id',
    `name` varchar(8) not null default '' comment '交易分类描述',
    `refundable` tinyint(1) unsigned not null  comment '是否可退款: 0 不可退款 1 可退款',
    `from_cat` tinyint(1) unsigned not null default 0 comment '流向目标分类: 0 本网站用户, 1 第三方支付机构 2 银行',
    `to_cat` tinyint(1) unsigned not null default 0 comment '流向目标分类: 0 本网站用户, 1 第三方支付机构 2 银行',
    `created_at` int(10) not null default 0 comment '记录创建时间',
    `updated_at` int(10) not null default 0 comment '记录更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易分类表';


drop table if exists `trans`;
create table `trans` (
    `id` bigint unsigned not null primary key auto_increment comment '交易流水id',
    `trans_id_ext` bigint unsigned not null default 0 comment '外部交易号id,如订单号,可为空',
    `is_enabled` tinyint(1) unsigned not null default 0 comment '账号状态: 1 有效 2 异常封禁 0为非法值',
    `trans_type_id` smallint unsigned not null comment '交易类型id',
    `settlement_type` tinyint(1) unsigned not null comment '结算类型: 1 实时结算 2 异步结算, 目前仅支持实时结算',
    `pay_mode` tinyint unsigned not null comment '交易模式: 1 中介担保支付, 2 直付交易 3 预付款 0 非法',
    `status` tinyint(1) not null default 0 comment '交易状态:1.等待付款 2.付款成功 3.交易成功:整个流程完成 4.退款中 5.退款完成',
    `from_uid` bigint(20) unsigned not null default '0' comment '交易发起方',
    `from_account_type` smallint unsigned not null default 1 comment '帐户类型:', 
    `from_username` bigint(20) unsigned not null default '0' comment '交易发起方账户名',
    `from_realname` bigint(20) unsigned not null default '0' comment '交易发起方真实姓名',
    `to_uid` bigint(20) unsigned not null default '0' comment '交易收到方',
    `to_account_type` smallint unsigned not null default 1 comment '帐户类型:', 
    `to_username` bigint(20) unsigned not null default '0' comment '交易收到方账户名',
    `to_realname` bigint(20) unsigned not null default '0' comment '交易收到方真实',
    `currency` tinyint(1) not null default 1 comment '币种:  1.人民币',
    `money` decimal(16,2) not null default 0.00 comment '支付给目标用户的金额',  
    `profit` decimal(16,2) not null default 0.00 comment '支付给目标用户的金额',  
    `fee` decimal(16,2) not null default 0.00 comment '交易费用，费用在交易产生时需要设置,可为0, 费用会在交易完成时结算',  
    `share_to_uid` bigint unsigned not null default 0 comment '支付第三个用户的利润分成,',  
    `share_fee` decimal(16,2) not null default 0.00 comment '分润金额, 分润会在交易完成时结算',  
    `earnest_money` decimal(16,2) not null default 0.00 comment '定金或者保证金，定金金额退款时不返还给买家,而是给目标客户',  
    `total_money` decimal(16,2) not null default 0.00 comment '交易总金额, 对于担保交易，会通过中间账户，成功后转到目标账户',  
    `description` varchar(32) not null default '' comment '交易描述',
    `created_at` int(10) not null default 0 comment '创建时间',
    `created_at_ext` int(10) not null default 0 comment '外部创建时间',
    `ended_at` int(10) not null default 0 comment '交易结束时间',
    `ended_at_ext` int(10) not null default 0 comment '外部交易结束时间',
    `payed_at` int(10) not null default 0 comment '支付完成时间,对于直接的账户交易，不存在支付时间，可为0',
    `payed_at_ext` int(10) not null default 0 comment '外部支付完成时间',
    `confirmed_at` int(10) not null default 0 comment '交易确认时间戳,交易确认对担保交易有效,对于直接交易',
    `confirmed_at_ext` int(10) not null default 0 comment '外部交易确认时间',
    `updated_at` int(10) not null default 0 comment '最后更新时间',
    `updated_at_ext` int(10) not null default 0 comment '外部最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易表';

drop table if exists `trans_log`;
create table `trans_log` (
    `id` int primary key not null comment '自增id',
    `trans_id` bigint unsigned not null comment '交易id,此交易号对平台唯一',
    `action` varchar(12)  not null default '' comment '动作',
    `money` decimal(9,2) not null default 0.00 comment '交易金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `op_id` bigint unsigned not null default 1 comment '操作人员id',
    `memo` varchar(24)  not null default '' comment '备注',
    `created_at` int(10) not null default 0 comment '记录创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易日志表';

drop table if exists `bill`;
create table `bill` (
    `id` bigint primary key not null comment '自增id',
    `uid` bigint(20) unsigned not null default 0 comment '用户id',
    `trans_id` bigint unsigned not null comment '账单来源交易id',
    `trans_type_id` smallint unsigned not null comment '账单来源交易类型',
    `trans_type_name` varchar(8) not null default '' comment '交易类型:1.充值或提现 2.购买行为(关联订单) 3.转账(平台内账户内转账, 4 退款)',
    `balance_type` tinyint(1) not null default 1 comment '资金变动的方向 1 表示收入，2表示支出',
    `money` decimal(16,2) not null default 0.00 comment '账单金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `description` varchar(32) not null default '' comment '账单描述',
    `created_at` int(10) not null default 0 comment '创建时间',
    key uidx_uid_trans (`uid`, `balance_type`),
    key uidx_uid_trans_tt (`uid`, `trans_type_id`, `balance_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='账单表';


drop table if exists `freeze`;
create table `freeze` (
    `id` bigint primary key not null comment '自增id',
    `freeze_type` smallint unsigned not null default 1 comment '冻结类型: 1 提现',
    `status` tinyint(1) not null default 0 comment '账号使用状态: 1 冻结中 2 已完成交易 3 交易失败已返还原账户',
    `uid` bigint(20) unsigned not null default 0 comment '用户id',
    `trans_id` bigint unsigned not null comment '账单来源交易id',
    `money` decimal(16,2) not null default 0.00 comment '冻结金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `thawed_at` int(10) unsigned not null default 0 comment '实际解冻时间',
    `description` varchar(32) not null default '' comment '账单描述',
    `created_at` int(10) not null default 0 comment '创建时间',
    `updated_at` int(10) not null default 0 comment '创建时间',
    key idx_trans (`trans_id`),
    key uidx_uid_ct (`uid`, `created_at`),
    key uidx_uid_trans (`uid`, `trans_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='冻结资金表';


drop table if exists `trans_refund_log`;
create table `trans_refund_log` (
    `id` bigint primary key not null auto_increment comment '自增id',
    `uid` bigint(20) unsigned not null default 0 comment '用户id',
    `trans_id` bigint unsigned not null comment '账单来源交易id',
    `money` decimal(16,2) not null default 0.00 comment '冻结金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `description` varchar(32) not null default '' comment '账单描述',
    `created_at` int(10) not null default 0 comment '创建时间',
    `updated_at` int(10) not null default 0 comment '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易退款历史表';

