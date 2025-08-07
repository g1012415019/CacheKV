<?php
return array(
    "cache" => array(
        "ttl" => 3600,
        "enable_stats" => false,  // 禁用统计功能
        "hot_key_auto_renewal" => false,  // 禁用热点键功能
    ),
    "key_manager" => array(
        "app_prefix" => "testapp",
        "separator" => ":",
        "groups" => array(
            "user" => array(
                "prefix" => "user",
                "version" => "v1",
                "keys" => array(
                    "profile" => array(
                        "template" => "profile:{id}",
                        "cache" => array("ttl" => 7200)
                    ),
                    "settings" => array(
                        "template" => "settings:{id}",
                        "cache" => array("ttl" => 3600)
                    )
                )
            ),
            "goods" => array(
                "prefix" => "goods",
                "version" => "v1",
                "keys" => array(
                    "info" => array(
                        "template" => "info:{id}",
                        "cache" => array("ttl" => 1800)
                    )
                )
            )
        )
    )
);
