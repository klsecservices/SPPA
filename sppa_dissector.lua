--SPPA S7 Data port dissector

p_sppa = Proto("sppa","SPPA S7")

-----------------------------------------------------------------------------------

local telegram_jobs = {  
    [101] = "Subscribe HMI",
    [102] = "Unsubscribe HMI",
    [103] = "Unsubscribe All", 
    [105] = "Get Value", 
    [111] = "Process value", 
    [112] = "Process value archive", 
    [151] = "Operate value (discret)",
    [152] = "Operate value (analog)",
    [153] = "Alarm ack", 
    [154] = "Alarm supress",
    [161] = "PtP value (discret)",
    [162] = "PtP value (analog)",
    [171] = "Sync event",
    [200] = "Switch connection",
    [301] = "Alarms", 
    [400] = "Lifebeat", 
    [1000] = "Telegram ack" 
}

local PtP_analog_types = { [0] = "Float", [64] = "Integer" }

local PhP_states1 = { [0] = "BAD NON SPECIFIC", [1] = "BAD CONFIGURATION ERROR", [2] = "BAD NOT CONNECTED",
    [3] = "BAD DEVICE FAILURE", [4] = "BAD SENSOR FAILURE", [5] = "BAD LAST KNOWN VALUE", 
    [6] = "BAD COMM FAILURE", [7] = "BAD OUT OF SERVICE", [16] = "UNCERTAIN NON SPECIFIC",
    [17] = "UNCERTAIN LAST USABLE VALUE", [20] = "UNCERTAIN SENSOR NOT ACCURATE",
    [21] = "UNCERTAIN ENGG UNITS EXCEEDED", [22] = "UNCERTAIN SUB NORMAL",
    [48] = "GOOD NON SPECIFIC", [54] = "GOOD LOCAL OVERRIDE", [56] = "GOOD UNCONNECTED" 
}

local PhP_states2 = { [0] = "LIMIT OK", [1] = "LIMIT LOW", [2] = "LIMIT HIGH", [3] = "LIMIT CONST" }

local alarm_offsets = { [2] = 1, [4] = 2, [8] = 3, [16] = 4, [32] = 5, [64] = 6}

local sync_event = {[0x100] = "Sync Event Archive", [0x200] = "Sync Event Alarm"}

local vt = {
    seq = ProtoField.uint32("sppa.seq", "Sequence number", base.DEC, nil, nil),
    seq_suf = ProtoField.uint32("sppa.seq_suf", "Sequence number (suffix)", base.DEC, nil, nil),
    ack = ProtoField.uint32("sppa.ack", "Acknowledgement number", base.DEC, nil, nil),
    length = ProtoField.uint32("sppa.length", "Total length", base.DEC, nil, nil),
    container_id = ProtoField.uint16("sppa.container_id", "Container ID", base.DEC, nil, nil),
    simatic_id = ProtoField.uint16("sppa.simatic_id", "Simatic ID", base.DEC, nil, nil),
    timestamp_1 = ProtoField.string("sppa.timestamp_1", "Timestamp (type 1)", base.STRING),
    timestamp_2 = ProtoField.string("sppa.timestamp_2", "Timestamp (type 2)", base.STRING),
    flag = ProtoField.uint8("sppa.flag", "Flags", base.HEX, nil, nil),
    err_flag = ProtoField.uint8("sppa.err_flag", "Error flags", base.HEX, nil, nil),
    --00, 03, ff
    job = ProtoField.uint16("sppa.job", "Job type", base.DEC, telegram_jobs, nil),
    ack_err = ProtoField.uint16("sppa.ack_err", "Acknowledge error", base.HEX, nil, nil),
    ack_suberr = ProtoField.uint16("sppa.ack_suberr", "Acknowledge suberror", base.HEX, nil, nil),
    resource_cnt = ProtoField.uint16("sppa.resource_cnt", "Recource count", base.DEC, nil, nil),
    resource_id = ProtoField.uint64("sppa.resource_id", "Recource ID", base.HEX, nil, nil),
    ptp_resource_id = ProtoField.uint16("sppa.ptp_resource_id", "PtP Recource ID", base.HEX, nil, nil),
    ptp_resource_idx = ProtoField.uint16("sppa.ptp_resource_idx", "PtP Recource index", base.DEC, nil, nil),
    ptp_value_state1 = ProtoField.uint8("sppa.ptp_value_state1", "PtP Value state 1", base.DEC, PhP_states1, 0xFC),
    ptp_value_state2 = ProtoField.uint8("sppa.ptp_value_state2", "PtP Value state 2", base.DEC, PhP_states2, 0x03),
    ptp_discrete_vl = ProtoField.uint8("sppa.ptp_discrete_vl", "PtP discrete value", base.DEC, nil, nil),
    ptp_analog_type = ProtoField.uint8("sppa.ptp_analog_type", "PtP analog type", base.DEC, PtP_analog_types, nil),
    ptp_analog_vli = ProtoField.uint32("sppa.ptp_analog_vli", "PtP analog value (integer)", base.DEC, nil, nil),
    ptp_analog_vlf = ProtoField.float("sppa.ptp_analog_vlf", "PtP analog value (float)", base.FLOAT, nil, nil),
    -- process value
    pv_length = ProtoField.uint16("sppa.pv.len", "Process value data length", base.DEC, nil, nil),
    pv_value_discrete = ProtoField.uint16("sppa.pv.value.discrete", "Process discrete value", base.DEC, nil, nil),
    pv_value_analog_int = ProtoField.uint32("sppa.pv.value.analog.int", "Process analog value (int)", base.DEC, nil, nil),
    pv_value_analog_float = ProtoField.float("sppa.pv.value.analog.float", "Process analog value (float)", base.FLOAT, nil, nil),
    -- General Request (Sync Event Telegram)
    sync_flags =  ProtoField.uint16("sppa.sync.flags", "Sync event flags (job flags)", base.DEC, sync_event, 0x300),
    --alarm
    alarm_len = ProtoField.uint16("sppa.alarm.len", "Alarm data length", base.DEC, nil, nil),
    alarm_id = ProtoField.uint32("sppa.alarm.id", "Alarm resource ID", base.HEX, nil, nil),
    alarm_val = ProtoField.uint8("sppa.alarm.val", "Alarm binary value", base.HEX, nil, nil),
    alarm_qcode = ProtoField.uint8("sppa.alarm.qcode", "Alarm QCode", base.HEX, nil, nil),
    alarm_offset = ProtoField.uint8("sppa.alarm.offset", "Alarm offset", base.DEC, alarm_offsets, nil),


    data = ProtoField.bytes("sppa.data", "Data")
}

p_sppa.fields = {vt["seq"], vt["seq_suf"], vt["ack"], vt["length"], vt["container_id"], vt["simatic_id"], 
    vt["timestamp_1"], vt["timestamp_2"], vt["err_flag"], vt["flag"], vt["job"], vt["ack_err"], 
    vt["ack_suberr"], vt["resource_cnt"], vt["resource_id"], vt["ptp_resource_id"], vt["ptp_resource_idx"],
    vt["ptp_value_state1"], vt["ptp_value_state2"], vt["ptp_discrete_vl"], vt["ptp_analog_type"], 
    vt["ptp_analog_vli"], vt["ptp_analog_vlf"], vt["sync_flags"],
    -- Alarm
    vt["alarm_len"], vt["alarm_id"], vt["alarm_val"], vt["alarm_qcode"], vt["alarm_offset"],
    -- Process Value
    vt["pv_length"], vt["pv_value_discrete"], vt["pv_value_analog_int"], vt["pv_value_analog_float"], 


    vt["data"]
}

function parse_timestamp(buf, pinfo, subtree)
    if buf(0,1):uint() == 0x19 then
        subtree:add(vt["timestamp_2"], string.format("20%02x.%02x.%02x %02x:%02x:%02x", 
            buf(0,1):uint(), 
            buf(1,1):uint(), 
            buf(2,1):uint(),
            buf(3,1):uint(),
            buf(4,1):uint(),
            buf(5,1):uint()
        ))
    else
        local ms = buf(0,4):uint()
        subtree:add(vt["timestamp_1"], string.format("%02d:%02d:%02d %dms",
            ms / (1000 * 60 * 60),
            ms / (1000 * 60) % 60,
            ms / (1000) % 60,
            ms % 1000
        ))
    end
end


function p_sppa.dissector(tvb, pinfo, tree)
	pinfo.cols.protocol = p_sppa.name 
    pinfo.cols.info:clear()
    pinfo.cols.info:append(string.format("%s -> %s\t", pinfo.src_port, pinfo.dst_port))

    if tvb:len() == 0 then return end
    
    local sppa_tree = tree:add(tvb(0),"SPPA")
    
    local sppa_header_tree = sppa_tree:add(tvb(0,22),"SPPA Header")
    sppa_header_tree:add(vt["seq"], tvb(0,4))
    sppa_header_tree:add(vt["length"], tvb(4,4))
    sppa_header_tree:add(vt["container_id"], tvb(8,2))
    sppa_header_tree:add(vt["simatic_id"], tvb(10,2))
    parse_timestamp(tvb(12,8), pinfo, sppa_header_tree)
    sppa_header_tree:add(vt["flag"], tvb(20,1))
    sppa_header_tree:add(vt["err_flag"], tvb(21,1))
    
    local seq_offset = tvb(4,4):uint() - 4 
    local sppa_data_tree = sppa_tree:add(tvb(22, seq_offset - 22), 
                                         string.format("Data: %s", telegram_jobs[tvb(22,2):uint()]))
    pinfo.cols.info:append(string.format("%s\t", telegram_jobs[tvb(22,2):uint()]))
    
    local job_id = tvb(22,2):uint()
    --sppa_data_tree:add(vt["job"], tvb(22,2))
    
    -- Subscribe packet
    if job_id == 101 or job_id == 102 or job_id == 103 then
        sppa_data_tree:add(vt["job"], tvb(22,2))
        sppa_data_tree:add(vt["resource_cnt"], tvb(24,2))
        local res_count = tvb(24,2):uint() - 1
        for i = 0, res_count do 
            sppa_data_tree:add(vt["resource_id"], tvb(26 + i * 8, 8))
        end
    
    elseif job_id == 105 then
        sppa_data_tree:add(vt["job"], tvb(22,2))
        sppa_data_tree:add(vt["resource_id"], tvb(24, 8))

    -- Process Telegram
    elseif job_id == 111 or job_id == 112 then
        local procval_data_offset = 22
        local procval_len = 0
        local sppa_procval_tree = nil
        while procval_data_offset < seq_offset do
            procval_len = tvb(procval_data_offset + 2, 2):uint()
            sppa_procval_tree = sppa_data_tree:add(tvb(procval_data_offset, procval_len), 
                                               string.format("Data Block: %s", 
                                                             telegram_jobs[tvb(procval_data_offset,2):uint()]))
            sppa_procval_tree:add(vt["job"], tvb(procval_data_offset, 2))
            sppa_procval_tree:add(vt["pv_length"], tvb(procval_data_offset + 2, 2))
            sppa_procval_tree:add(vt["data"], tvb(procval_data_offset + 4, procval_len - 4))
            procval_data_offset = procval_data_offset + procval_len
        end
    -- Operate Telegram
    elseif job_id == 151 or job_id == 152 then
        local oper_data_offset = 22
        local sppa_oper_tree = nil
        while oper_data_offset < seq_offset do
            local oper_len = 14
            if job_id == 151 then
                oper_len = 12
            end
            sppa_oper_tree = sppa_data_tree:add(tvb(oper_data_offset, oper_len), 
                                               string.format("Data Block: %s", 
                                                             telegram_jobs[tvb(oper_data_offset, 2):uint()]))
            sppa_oper_tree:add(vt["job"], tvb(oper_data_offset, 2))
            sppa_oper_tree:add(vt["resource_id"], tvb(oper_data_offset + 2, 8))
            if job_id == 151 then
                sppa_oper_tree:add(vt["pv_value_discrete"], tvb(oper_data_offset + 10, 2))
                oper_data_offset = oper_data_offset + 12
            else
                sppa_oper_tree:add(vt["pv_value_analog_int"], tvb(oper_data_offset + 10, 4))
                oper_data_offset = oper_data_offset + 14
            end
        end
          
    -- Alarm State Telegram
    elseif job_id == 153 or job_id == 154 then
        local alrst_data_offset = 22
        local sppa_alrst_tree = nil
        while alrst_data_offset < seq_offset do
            sppa_alrst_tree = sppa_data_tree:add(tvb(alrst_data_offset, 12), 
                                               string.format("Data Block: %s", 
                                                            telegram_jobs[tvb(alrst_data_offset, 2):uint()]))
            sppa_alrst_tree:add(vt["job"], tvb(alrst_data_offset, 2))
            sppa_alrst_tree:add(vt["resource_id"], tvb(alrst_data_offset + 2, 8))
            sppa_alrst_tree:add(vt["alarm_offset"], tvb(alrst_data_offset + 10, 2))
            alrst_data_offset = alrst_data_offset + 12
        end

    -- PtP Telegram
    elseif job_id == 161 or job_id == 162 then
        local PtP_data_offset = 22
        local sppa_ptp_tree = nil
        while PtP_data_offset < seq_offset do
            local ptp_len = 12
            if job_id == 161 then
                ptp_len = 8
            end
            sppa_ptp_tree = sppa_data_tree:add(tvb(PtP_data_offset, ptp_len), 
                                               string.format("PtP Resource ID: 0x%04x", tvb(PtP_data_offset + 2, 2):uint()))
            sppa_ptp_tree:add(vt["job"], tvb(PtP_data_offset,2))
            sppa_ptp_tree:add(vt["ptp_resource_id"], tvb(PtP_data_offset + 2, 2))
            sppa_ptp_tree:add(vt["ptp_resource_idx"], tvb(PtP_data_offset + 4, 2))
            local sppa_ptp_state_tree = sppa_ptp_tree:add(tvb(PtP_data_offset + 6, 1), 
                                                          string.format("PtP value state: %s; %s", 
                                                            PhP_states1[bit.rshift(bit.band(tvb(28,1):uint(), 0xFC), 2)],
                                                            PhP_states2[bit.band(tvb(28,1):uint(), 0x3)]))
            sppa_ptp_state_tree:add(vt["ptp_value_state1"], tvb(PtP_data_offset + 6, 1))
            sppa_ptp_state_tree:add(vt["ptp_value_state2"], tvb(PtP_data_offset + 6, 1))

            if job_id == 161 then
                sppa_ptp_tree:add(vt["ptp_discrete_vl"], tvb(PtP_data_offset + 7, 1))
                PtP_data_offset = PtP_data_offset + 8
            else
                sppa_ptp_tree:add(vt["ptp_analog_type"], tvb(PtP_data_offset + 7, 1))
                if tvb(PtP_data_offset + 7, 1):uint() == 0 then
                    sppa_ptp_tree:add(vt["ptp_analog_vlf"], tvb(PtP_data_offset + 8, 4))
                else
                    sppa_ptp_tree:add(vt["ptp_analog_vli"], tvb(PtP_data_offset + 8, 4))
                end
                PtP_data_offset = PtP_data_offset + 12
            end

        end

    -- General request Telegram (Sync Event Telegram)
    elseif job_id == 171 then
        sppa_data_tree:add(vt["job"], tvb(22,2))
        sppa_data_tree:add(vt["sync_flags"], tvb(24,2))
    
    -- Alarm
    elseif job_id == 301 then
        local alarm_data_offset = 22
        local sppa_alarm_tree = nil
        while alarm_data_offset < seq_offset do
            alarm_len = tvb(24, 2):uint()
            sppa_alarm_tree = sppa_data_tree:add(tvb(alarm_data_offset, alarm_len), "Alarm frame")
            sppa_alarm_tree:add(vt["job"], tvb(alarm_data_offset, 2))
            sppa_alarm_tree:add(vt["alarm_len"], tvb(alarm_data_offset + 2, 2))
            parse_timestamp(tvb(alarm_data_offset + 4, 4), pinfo, sppa_alarm_tree)

            alarm_resource_offset = alarm_data_offset + 8
            while alarm_resource_offset < alarm_data_offset + alarm_len do
                sppa_alarm_resource_tree = sppa_alarm_tree:add(tvb(alarm_resource_offset, 6), 
                                                   string.format("Alarm Resource ID: 0x%04x", 
                                                                 tvb(alarm_resource_offset,4):uint()))
                sppa_alarm_resource_tree:add(vt["alarm_id"], tvb(alarm_resource_offset, 4))
                alarm_resource_offset = alarm_resource_offset + 4
                sppa_alarm_resource_tree:add(vt["alarm_val"], tvb(alarm_resource_offset, 1))
                alarm_resource_offset = alarm_resource_offset + 1
                sppa_alarm_resource_tree:add(vt["alarm_qcode"], tvb(alarm_resource_offset, 1))
                alarm_resource_offset = alarm_resource_offset + 1
            end
            alarm_data_offset = alarm_data_offset + alarm_len
        end

    -- Lifebeat
    elseif job_id == 400 then
        sppa_data_tree:add(vt["job"], tvb(22,2))

    elseif job_id == 1000 then 
        sppa_data_tree:add(vt["job"], tvb(22,2))
        sppa_data_tree:add(vt["ack"], tvb(24,4))
        sppa_data_tree:add(vt["ack_err"], tvb(28,2))
        sppa_data_tree:add(vt["ack_suberr"], tvb(30,2))
    end

    sppa_tree:add(vt["seq_suf"], tvb(seq_offset, 4))
end



local udp_dissector_table = DissectorTable.get("udp.port")
udp_dissector_table:add(10001, p_sppa)
udp_dissector_table:add(10002, p_sppa)
udp_dissector_table:add(10003, p_sppa)

