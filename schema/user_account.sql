pay_type 充值的手段(对于网站平台来说，用户的pay属于充值)

user_pay_log 用户支付日志:pay对应的是站内的金额交易，设计到现金流的，订单有可能不涉及现金流,该表仅仅是现金流:交易明细 交易明细的历史记录可更改,但是是有系统更改 ,交易需要有交易流水号,不同的支付会有不同的支付流水号,如转账，购买，付款等等

user_account 记录用户目前账户状态,可用现金和不可用的现金以及总资金,不设置单独的虚拟余额

user_account_log  账户日志表,类似于账户收支明细表,包含了收入和支出,记录每次现金流账户的余额信息,用户购买订单如果用到银行卡充值或者第三方平台充值的，算属于先充值，后付款的方式:即：用户用支付宝付款购买产品相当于，先充值到网站账号，之后再支出，分为充值和支出两个步骤, 账户收支明细属于历史记录，不能更改,现金状态表

------------
-- 用户表，用户的现金账户表, 标示用户目前的账户目前状态,可用额度,以及其他状态,有几个账号需要保留
-- 手续费收益账号，分润账号，担保交易中间账号,银行账号,分润帐号是公司收益账号, 如何实现账号分开管理,特殊账号，用户账号
--商户账号和银行账号，以及普通账号, 分润账号和手续费账号公用一个账号, 100000 以内账号系统保留，100000以上账号为用户账号, 用户表的初始自增值为100000
------------

drop table if exists `user_account`;
create table `user_account` (
    `uid` bigint unsigned primary key comment '用户id',  
    `type` tinyint unsigned not null default 1 comment '帐户类别: 1.普通用户账号 2.公司账号 3.银行账号', 
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币'
    `enabled` tinyint(1) unsigned not null default 0 comment '账号状态: 1 有效 2 异常封禁 0为非法值',
    `balance` decimal(16,2) not null default 0.00 comment '用户账户现金余额', 
    `deposit` decimal(16,2) not null default 0.00 comment '保证金', 
    `frozon_money` decimal(16,2) not null default 0.00 comment '冻结资金', 
    `pay_password` varchar(64) not null default '' comment '支付密码',
    `last_balance_changed_ip` char(15) not null default '' comment '上次帐户余额更新ip'
    `last_balance_changed_at` char(12) not null default '' comment '上次帐户结余变更时间'
    `created_at` int(10) unsigned not null default 0 comment '账户开启时间', 
    `updated_at` int(10) not null default 0 comment '账户更新时间',
    key uidx_uid_actype (`uid`, `account_type`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户现金账户表';

------------
-- 用户账户更新明细日志表,标示用户的账户状态变化历史,如果需要知道变更详细信息，需要根据transaction_id到transaction表中去查,类似于账户快照
------------

drop table if exists `user_account_log`;
create table `user_account_log` (
    `id` bigint unsigned primary key comment '自增账户日志id -- 流水id',  
    `uid` int unsigned not null comment '用户id',  
    `account_type` smallint unsigned not null default 1 comment '帐户类型:', 
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币'
    `trans_id` varchar(32) not null default '' comment '和变动关联的交易单号',
    `balance` decimal(16,2) not null default 0.00 comment '用户账户剩余资金', 
    `deposit` decimal(16,2) not null default 0.00 comment '用户冻结资金', 
    `balance_type` tinyint(1) unsigned not null default 1 comment '变动方向 1 账户金额增加 2 账户余额减少 其他非法',
    `tans_money` decimal(16,2) not null default 0.00 comment '变动金额',
    `tans_desc` varchar(64) not null default '' comment '变动描述',
    `created_at` int(10) unsigned not null default 0 comment '资金流动行为产生时间',
    key uidx_uid_actype (`uid`, `account_type`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户账户历史表';

------------
-- 特殊账户，特殊账户主要包含公司收益账户，银行账户等,是否需要单独建立一个表，还是重用user_account表，
--对于分布式数据库，分离式好的，因为每个数据库可以为之自己的序列,特殊账号可以在一个节点上存在。
-- 
------------
drop table if exists `special_account`;
create table `special_account` (
    `uid` bigint unsigned primary key comment '用户id',  
    `account_type` smallint unsigned not null default 1 comment '帐户类型: 一个用户可以有多个不同类型账号，目前默认是1 ', 
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币'
    `enabled` tinyint(1) unsigned not null default 0 comment '账号状态: 1 有效 2 异常封禁 0为非法值',
    `balance` decimal(16,2) not null default 0.00 comment '用户账户现金余额', 
    `deposit` decimal(16,2) not null default 0.00 comment '保证金', 
    `frozon_money` decimal(16,2) not null default 0.00 comment '冻结资金', 
    `pay_password` varchar(64) not null default '' comment '支付密码',
    `last_balance_change_ip` char(15) not null default '' comment '上次帐户余额更新ip'
    `last_balance_changed_at` char(12) not null default '' comment '上次帐户结余变更时间'
    `created_at` int(10) unsigned not null default 0 comment '账户开启时间', 
    `updated_at` int(10) not null default 0 comment '账户更新时间',
    key uidx_uid_actype (`uid`, `account_type`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='特殊用户现金账户表';

------------
-- 交易分类表,发起交易时，会根据交易分类来设置交易双方的信息，加上交易种类来判断如何生成账单(bill),如果是用户之间转账或者
-- 用户之间的交易，双方都会生成bill, 生成的时机会不同，如购买产品，发起方会直接生成bill并扣款成功，收到方只有当订单完成的时候才生成bill
------------

drop table if exists `trans_type`;
create table `trans_type` (
    `id` smallint unsigned primary key not null comment '自增id',
    `name` varchar(8) not null default '' comment '交易分类描述',
    `refundable` tinyint(1) unsigned not null  comment '是否可退款: 0 不可退款 1 可退款',
    `from_cat` tinyint(1) unsigned not null default 0 comment '流向目标分类: 0 本网站用户, 1 第三方支付机构 2 银行',
    `to_cat` tinyint(1) unsigned not null default 0 comment '流向目标分类: 0 本网站用户, 1 第三方支付机构 2 银行',
    `created_at` int(10) not null default 0 comment '记录创建时间',
    `updated_at` int(10) not null default 0 comment '记录更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易分类表';

------------
-- 交易 交易是账户状态变化的源泉，充值，购买，退款等等都是交易的一种,如果牵扯到第三方支付，transaction表是支付的源泉,type决定了交易双方的类型,交易双方的id，需要根据交易分类来判断,转账，双方都是用户id, 充值，from_id是支付渠道id, to_id是用户id, 提现from_id是用户id,to_id是用户绑定银行卡id,对于担保交易，金额会存进中间账号中，当确认付款之后，由中间账号打入目的地账号
------------

drop table if exists `trans`;
create table `trans` (
    `trans_id` varchar(32) not null primary key  comment '交易流水id',
    `trans_id_ext` varchar(32) not null default  comment '外部交易号id,如订单号,可为空',
    `enabled` tinyint(1) unsigned not null default 0 comment '账号状态: 1 有效 2 异常封禁 0为非法值',
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
    `updated_at` int(10) not null default 0 comment '最后更新时间'
    `updated_at_ext` int(10) not null default 0 comment '外部最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易表';

-----------
-- 交易历史表 记录trans的操作记录, 对用户不可见
------------
drop table if exists `trans_log`;
create table `trans_log` (
    `id` int primary key not null comment '自增id',
    `trans_id` bigint unsigned not null default '' comment '交易id,此交易号对平台唯一',
    `action` varchar(12)  not null default '' comment '动作',
    `money` decimal(9,2) not null default 0.00 comment '交易金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `op_id` bigint unsigned not null default 1 comment '操作人员id',
    `memo` varchar(24)  not null default '' comment '备注',
    `created_at` int(10) not null default 0 comment '记录创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易日志表';

------------
-- 账单，账单是每个用户入账或者是出账的记录，一个交易大部分会有两条账单记录，一条发起方，一条收入方, 账单的生成虽不同的
-- 交易模式有不同的生成方式,产生bill的渠道有 购买 转账 分润 提现 退款 充值等等, 和trans不同，一个trans可能会产生多个bill,
-- 牵扯到的各方都会收到账单 
-- transaction属于只记录现金的流动方式，bill记录每个人自己所属的资金流动情况
------------

drop table if exists `bill`;
create table `bill` (
    `id` bigint primary key not null comment '自增id',
    `uid` bigint(20) unsigned not null default 0 comment '用户id',
    `trans_id` bigint unsigned not null default '' comment '账单来源交易id',
    `trans_type_id` smallint unsigned not null default '' comment '账单来源交易类型',
    `trans_type_name` varchar(8) unsigned not null default 0 comment '交易类型:1.充值或提现 2.购买行为(关联订单) 3.转账(平台内账户内转账, 4 退款)',
    `balance_type` tinyint(1) not null default 1 comment '资金变动的方向 1 表示收入，2表示支出',
    `money` decimal(16,2) not null default 0.00 comment '账单金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币'
    `description` varchar(32) not null default '' comment '账单描述',
    `created_at` int(10) not null default 0 comment '创建时间',
    key uidx_uid_trans ('uid', 'balance_type'),
    key uidx_uid_trans ('uid', 'trans_type_id', 'balance_type')
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='账单表';

------------
-- 冻结资金表,此表用户不可见,冻结资金用于用户的提现等操作(主要是提现),提现成功完成之后，会从用户的冻结资金中扣除,该表可理解为用户冻结资金
-- 担保交易中的金钱放在中间账号上,而不是冻结在该表中,可以理解为在途资金
------------

drop table if exists `freeze`;
create table `freeze` (
    `id` bigint primary key not null comment '自增id',
    `freeze_type` smallint unsigned not null default 1 comment '冻结类型: 1 提现',
    `status` tinyint(1) not null default 0 comment '账号使用状态: 1 冻结中 2 已完成交易 3 交易失败已返还原账户',
    `uid` bigint(20) unsigned not null default 0 comment '用户id',
    `trans_id` bigint unsigned not null default '' comment '账单来源交易id',
    `money` decimal(16,2) not null default 0.00 comment '冻结金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `thawed_at` int(10) unsigned not null default 0 comment '实际解冻时间',
    `description` varchar(32) not null default '' comment '账单描述',
    `created_at` int(10) not null default 0 comment '创建时间',
    `updated_at` int(10) not null default 0 comment '创建时间',
    key idx_trans ('trans_id'),
    key uidx_uid_ct ('uid', 'created_at'),
    key uidx_uid_trans ('uid', 'trans_id')
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='冻结资金表';

------------
-- 退款日志表:仅记录退款的日志,并非所有的交易都可以退款,用户交易可退款，退款需要退利润，手续费等
------------

drop table if exists `trans_refund_log`;
create table `trans_refund_log` (
    `id` int primary key not null comment '自增id',
    `uid` bigint(20) unsigned not null default 0 comment '用户id',
    `trans_id` bigint unsigned not null default '' comment '账单来源交易id',
    `money` decimal(16,2) not null default 0.00 comment '冻结金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币',
    `description` varchar(32) not null default '' comment '账单描述',
    `created_at` int(10) not null default 0 comment '创建时间',
    `updated_at` int(10) not null default 0 comment '创建时间',
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='交易退款历史表';


------------
-- profit_bill 收益账单，账单是每个用户入账或者是出账的记录，一个交易大部分会有两条账单记录，一条发起方，一条收入方, 账单的生成虽不同的, profit类型有: 1 手续费  2 利润分成, 对于收益账单，单独用一个表来储存，因为此表的记录数量会比较大,但是查询的次数不多
------------
drop table if exists `profit_bill`;
create table `profit_bill` (
    `id` int primary key not null comment '自增id',
    `profit_type` tinyint not null comment '利润来源: 1 手续费产生利润 2 订单利润分成',
    `uid` bigint(20) unsigned not null default 0 comment '用户id',
    `trans_id` bigint unsigned not null default '' comment '账单来源交易id',
    `trans_type_id` smallint unsigned not null default '' comment '账单来源交易类型',
    `trans_type_name` varchar(8) unsigned not null default 0 comment '交易类型:1.充值或提现 2.购买行为(关联订单) 3.转账(平台内账户内转账, 4 退款)',
    `balance_type` tinyint(1) not null default 1 comment '资金变动的方向 1 表示收入，2表示支出',
    `money` decimal(16,2) not null default 0.00 comment '账单金额',  
    `currency` tinyint unsigned not null default 1 comment '币种: 1 人民币'
    `description` varchar(32) not null default '' comment '账单描述',
    `created_at` int(10) not null default 0 comment '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='利润账单表';

