@extends('layouts.raid')

@section('content')
<div class="layui-tab layui-tab-card" lay-filter="advert-log">
    <ul class="layui-tab-title">
        <li class="layui-this" lay-tab-event="loadRaidLogs">Raid Logs</li>
        <li lay-tab-event="loadAdvertLogs">Advert Logs</li>
        <li lay-tab-event="loadRaids">Raids</li>
        <li lay-tab-event="loadGears">Gears</li>
        <li lay-tab-event="loadRaidKills">Raid Kills</li>
        <li lay-tab-event="loadGearKills">Gear Kills</li>
    </ul>
    <div class="layui-tab-content">
        <div class="layui-tab-item layui-show">
            <div style="padding: 15px;">
                <table id="raid-log"></table>
            </div>
        </div>
        <div class="layui-tab-item">
            <div style="padding: 15px;">
                <table id="advert-log"></table>
            </div>
        </div>
        <div class="layui-tab-item">
            <div style="padding: 15px;">
                <table id="raids"></table>
            </div>
        </div>
        <div class="layui-tab-item">
            <div style="padding: 15px;">
                <table id="gears"></table>
            </div>
        </div>
        <div class="layui-tab-item">
            <div style="padding: 15px;">
                <table id="raid-kills"></table>
            </div>
        </div>
        <div class="layui-tab-item">
            <div style="padding: 15px;">
                <table id="gear-kills"></table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    layui.use(["element", "table", "util"], function () {
        let table = layui.table;
        let util = layui.util;

        function loadRaidLogs() {
            table.render({
                elem:"#raid-log",
                url:"{{ @asset('api/raid/raid_logs') }}",
                skin:"line",
                even:true,
                page:true,
                limit:20,
                cols:[[
                    {field:"id", title:"ID", sort:true},
                    {field:"open_id", title:"OpenID"},
                    {field:"game_type", title:"GameType"},
                    {field:"raid_name", title:"RaidName"},
                    {field:"boss_name", title:"BossName"},
                    {field:"created_at", title:"CreatedAt"},
                ]]
            });
        }

        function loadAdvertLogs() {
            table.render({
                elem:"#advert-log",
                url:"{{ @asset('api/raid/adv_logs') }}",
                skin:"line",
                even:true,
                page:true,
                limit:20,
                cols:[[
                    {field:"id", title:"ID", sort:true},
                    {field:"open_id", title:"OpenID"},
                    {field:"num", title:"Num"},
                    {field:"created_at", title:"CreatedAt"},
                ]]
            });
        }

        function loadRaids() {
            table.render({
               elem:"#raids",
               url:"{{ @asset('api/raid/raids') }}",
               skin:"line",
               even:true,
               page:true,
               limit:20,
               cols:[[
                   {field:"id", title:"ID", sort:true},
                   {field:"raid_name", title:"RaidName"},
                   {field:"boss_name", title:"BossName"},
                   {field:"boss_level", title:"BossLevel"},
                   {field:"zb_name", title:"ZbName"},
                   {field:"zb_level", title:"ZbLevel"},
                   {field:"drop_rate", title:"DropRate"},
               ]]
            });
        }

        function loadGears() {
            table.render({
                elem:"#gears",
                url:"{{ @asset('api/raid/gears') }}",
                skin:"line",
                even:true,
                page:true,
                limit:20,
                cols:[[
                    {field:"id", title:"ID", sort:true},
                    {field:"raid_name", title:"RaidName"},
                    {field:"boss_name", title:"BossName"},
                    {field:"boss_level", title:"BossLevel"},
                    {field:"zb_name", title:"ZbName"},
                    {field:"zb_level", title:"ZbLevel"},
                    {field:"drop_rate", title:"DropRate"},
                ]]
            });
        }

        function loadRaidKills() {
            table.render({
                elem:"#raid-kills",
                url:"{{ @asset('api/raid/raid_kills') }}",
                skin:"line",
                even:true,
                cols:[[
                    {field:"raid_name", title:"副本"},
                    {field:"boss_name", title:"BOSS"},
                    {field:"kills", title:"击杀次数", sort:true}
                ]]
            });
        }

        function loadGearKills() {
            table.render({
                elem:"#gear-kills",
                url:"{{ @asset('api/raid/gear_kills') }}",
                skin:"line",
                even:true,
                cols:[[
                    {field:"raid_name", title:"副本"},
                    {field:"boss_name", title:"BOSS"},
                    {field:"kills", title:"击杀次数", sort:true}
                ]]
            });
        }

        loadRaidLogs();

        util.event("lay-tab-event", {
            loadRaidLogs: loadRaidLogs,
            loadAdvertLogs: loadAdvertLogs,
            loadRaids: loadRaids,
            loadGears: loadGears,
            loadRaidKills: loadRaidKills,
            loadGearKills: loadGearKills
        });
    });
</script>
@endpush
