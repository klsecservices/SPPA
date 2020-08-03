<?php
    $result_folder = './result/';
    $files = rglob('bw_tmeproxyafi/*');
    shuffle($files);
    foreach($files as $file){
        if(is_file($file) and substr($file,-6) == '.class') {
            echo 'Start '.$file.PHP_EOL;
            $dis = new DataInputStream($file);
            $constant_pool   = [];
            $tmp  = '';
            $tmp .= $dis->read(4);                                  /*magic*/
            $tmp .= $dis->read(2);                                  /*minor_version*/
            $tmp .= $dis->read(2);                                  /*major_version*/
            $tmp .= $dis->readWOSkip(2);                            /*constant_pool_count*/
            $constant_pool_count = $dis->readShort();
            $fix_index      = [];                                   /*переопределение индексов для perc*/
            $n_fix_index    = [];                                   /*отдельный индекс для чисел в perc*/
            $n_index = 0;
            $index   = 0;
            for($i = 0; $i < $constant_pool_count - 1; $i++) {
                $tmp .= $dis->readWOSkip(1);                        /*tag*/
                $tag = $dis->readByte();
                switch ($tag) {
                    case 7:
                    case 8:
                        $tmp .= $dis->read(2);                      /*CONSTANT_Class, CONSTANT_String*/
                        $index++;
                        $fix_index[$index] = $i + 1;
                        break;

                    case 16:
                        $tmp .= $dis->read(2);                      /*CONSTANT_MethodType*/
                        break;
             
                    case 9:
                    case 10:
                    case 11:
                        $tmp .= $dis->read(4);                      /*CONSTANT_Fieldref, CONSTANT_Methodref, CONSTANT_InterfaceMethodref*/
                        $index++;
                        $fix_index[$index] = $i + 1;
                        break;

                    case 3:
                    case 4:  
                    case 12:
                    case 18:
                        $tmp .= $dis->read(4);                      /*CONSTANT_Integer, CONSTANT_Float, CONSTANT_NameAndType, CONSTANT_InvokeDynamic*/
                        $n_fix_index[$n_index] = $i + 1;
                        $n_index++;
                        break;

                    case 5:
                    case 6: 
                        $tmp .= $dis->read(8);                      /*CONSTANT_Long, CONSTANT_Double*/
                        $n_fix_index[$n_index] = $i+1;
                        $n_index++;
                        $n_index++;
                        $i++;
                        break;  

                    case 1: 
                        $tmp .= $dis->readWOSkip(2);                /*CONSTANT_Utf8*/
                        $constant_pool[$i + 1] = $dis->read($dis->readShort());
                        $tmp .= $constant_pool[$i + 1];
                        break;

                    case 15: 
                        $tmp .= $dis->read(3);                      /*CONSTANT_MethodHandle*/
                        break; 

                    default:
                        error('Something wrong. Tag 0x'.dechex($tag));
                }
            }

            $tmp .= $dis->read(2);                                  /*access_flags*/
            $tmp .= $dis->read(2);                                  /*this_class*/
            $tmp .= $dis->read(2);                                  /*super_class*/

            $tmp .= $dis->readWOSkip(2);                            /*interfaces_count*/
            $interfaces_count = $dis->readShort();
            for($i = 0; $i < $interfaces_count; $i++) {
                $tmp .= $dis->read(2);                              /*interfaces*/
            }

            $tmp .= $dis->readWOSkip(2);                            /*fields_count*/
            $fields_count = $dis->readShort();
            for($i = 0; $i < $fields_count; $i++) {
                $tmp .= $dis->read(2);                              /*field access_flags*/
                $tmp .= $dis->read(2);                              /*field name_index*/
                $tmp .= $dis->read(2);                              /*field descriptor_index*/
                $tmp .= $dis->readWOSkip(2);                        /*field attributes_count*/
                $attributes_count = $dis->readShort();
                for($j = 0; $j < $attributes_count; $j++) {
                    $tmp .= $dis->read(2);                          /*field attribute_info attribute_name_index*/
                    $tmp .= $dis->readWOSkip(4);                    /*field attribute_info attribute_length*/
                    $tmp .= $dis->read($dis->readInt());            /*field attribute_info info*/
                }
            }

            $tmp .= $dis->readWOSkip(2);                            /*methods_count*/
            $methods_count = $dis->readShort();
            for($i = 0; $i < $methods_count; $i++) {
                $perc_code = '';
                $exceptions = [];
                $tmp .= $dis->read(2);                              /*method_info access_flags*/
                $tmp .= $dis->readWOSkip(2);                        /*method_info name_index*/
                $method_name_index = $dis->readShort();
                echo 'Method: '.$constant_pool[$method_name_index].PHP_EOL;
                $tmp .= $dis->read(2);                              /*method_info descriptor_index*/
                $tmp .= $dis->readWOSkip(2);                        /*method_info attributes_count*/
                $attributes_count = $dis->readShort();
                for($j = 0; $j < $attributes_count; $j++) {
                    $tmp .= $dis->readWOSkip(2);                    /*method_info attribute_info attribute_name_index*/
                    $attribute_name_index = $dis->readShort();
                    $attribute_length = $dis->readInt();            /*attribute_length*/
                    $attribute_info = $dis->read($attribute_length);
                    if($constant_pool[$attribute_name_index] == 'Code') {
                        $tmp2 = '';
                        $codeDis = new DataInputStream($attribute_info, false, true);
                        $tmp2 .= $codeDis->read(2);                             /*code_attribute max_stack*/
                        $tmp2 .= $codeDis->read(2);                             /*code_attribute max_locals*/
                        $codeLen = $codeDis->readInt();      
                        $code = $codeDis->read($codeLen);       
                        //echo 'Orig Code: '.bin2hex($code).PHP_EOL;
                        if(empty($perc_code))
                            die('Perc Code error');
                        $realCode = transformByteCode($perc_code, $code);
                        //echo 'Java Code: '.bin2hex($realCode).PHP_EOL;
                        $tmp2 .= chr((strlen($realCode) & 0xFF000000) >> 24);   /*code_attribute code_length*/
                        $tmp2 .= chr((strlen($realCode) & 0x00FF0000) >> 16);   /*code_attribute code_length*/
                        $tmp2 .= chr((strlen($realCode) & 0x0000FF00) >>  8);   /*code_attribute code_length*/
                        $tmp2 .= chr((strlen($realCode) & 0x000000FF)      );   /*code_attribute code_length*/
                        $tmp2 .= $realCode;                                     /*code_attribute code*/

                        $tmp2 .= chr(count($exceptions) >> 8);                  /*new exception_table*/
                        $tmp2 .= chr(count($exceptions) & 0xFF);
                        for($k = 0; $k < count($exceptions); $k++) {
                            $tmp2 .= $exceptions[$k]['start_pc'];               /*exception_table start_pc*/
                            $tmp2 .= $exceptions[$k]['end_pc'];                 /*exception_table end_pc*/
                            $tmp2 .= $exceptions[$k]['handler_pc'];             /*exception_table handler_pc*/
                            $tmp2 .= chr(0).chr(0);                             /*exception_table catch_type*/
                        }

                        $exception_table_length = $codeDis->readShort();        /*skip old*/
                        for($k = 0; $k < $exception_table_length; $k++) {
                            $codeDis->read(2);                                  /*exception_table start_pc*/              
                            $codeDis->read(2);                                  /*exception_table end_pc*/         
                            $codeDis->read(2);                                  /*exception_table handler_pc*/         
                            $codeDis->read(2);                                  /*exception_table catch_type*/         
                        }

                        $tmp2 .= $codeDis->readWOSkip(2);                       /*code_attribute attributes_count*/
                        $ca_attributes_count = $codeDis->readShort();         
                        for($k = 0; $k < $ca_attributes_count; $k++) {
                            $tmp2 .= $codeDis->read(2);                         /*attribute_info attribute_name_index*/  
                            $tmp2 .= $codeDis->readWOSkip(4);                   /*attribute_info attribute_length*/                              
                            $ca_at_length = $codeDis->readInt();                /*attribute_info attribute_length*/  
                            $tmp2 .= $codeDis->read($ca_at_length);             /*attribute_info attribute*/   
                        }
                        $tmp .= chr((strlen($tmp2) & 0xFF000000) >> 24);        /*method_info attribute_info attribute_length*/
                        $tmp .= chr((strlen($tmp2) & 0x00FF0000) >> 16);        /*method_info attribute_info attribute_length*/
                        $tmp .= chr((strlen($tmp2) & 0x0000FF00) >>  8);        /*method_info attribute_info attribute_length*/
                        $tmp .= chr((strlen($tmp2) & 0x000000FF)      );        /*method_info attribute_info attribute_length*/
                        $tmp .= $tmp2;                                          /*method_info attribute_info info*/
                    } else {
                        if($constant_pool[$attribute_name_index] == 'perc.pxl.Augment') {
                            $codeDis = new DataInputStream($attribute_info, false, true);
                            $codeDis->read(10);                                 /*Augment_attribute xz*/
                            $codeLen = $codeDis->readInt();                     /*Augment_attribute code_length*/
                            $perc_code = $codeDis->read($codeLen);
                            //echo 'Perc Code: '.bin2hex($perc_code).PHP_EOL;
                        } elseif($constant_pool[$attribute_name_index] == 'perc.pxl.Exceptions') {
                            $codeDis = new DataInputStream($attribute_info, false, true);
                            $exception_table_length = $codeDis->readShort();        /*Exceptions exception_table_length*/
                            for($k = 0; $k < $exception_table_length; $k++) {
                                $exceptions[$k]['start_pc'] =   $codeDis->read(2);  /*exception_table start_pc*/ 
                                $exceptions[$k]['end_pc'] =     $codeDis->read(2);  /*exception_table end_pc*/  
                                $exceptions[$k]['handler_pc'] = $codeDis->read(2);  /*exception_table handler_pc*/       
                            }
                        }
                        $tmp .= chr(($attribute_length & 0xFF000000) >> 24); /*method_info attribute_info attribute_length*/
                        $tmp .= chr(($attribute_length & 0x00FF0000) >> 16); /*method_info attribute_info attribute_length*/
                        $tmp .= chr(($attribute_length & 0x0000FF00) >>  8); /*method_info attribute_info attribute_length*/
                        $tmp .= chr(($attribute_length & 0x000000FF)      ); /*method_info attribute_info attribute_length*/
                        $tmp .= $attribute_info;                             /*method_info attribute_info info*/
                    }
                    
                }
            }

            $tmp .= $dis->readWOSkip(2);                            /*attributes_count*/
            $attributes_count = $dis->readShort();
            for($j = 0; $j < $attributes_count; $j++) {
                $tmp .= $dis->read(2);                              /*class attribute_info attribute_name_index*/
                $tmp .= $dis->readWOSkip(4);                        /*class attribute_info attribute_length*/
                $tmp .= $dis->read($dis->readInt());                /*class attribute_info info*/
            }

            $dir = dirname($result_folder.$file);
            if(!file_exists($dir))
                mkdir($dir,0777,true);
            file_put_contents($result_folder.$file, $tmp);
        }
    }
    echo 'Finish'.PHP_EOL;

    function transformByteCode($bytecode, $orgBytecode) {
        global $fix_index, $n_fix_index;

        $difOrgBytecodeOffset = 0;
        for($i = 0; $i < strlen($bytecode); $i++) {
            $opcode = ord($bytecode[$i]);
            switch ($opcode) {
                /*E8 + dup */
                case 0xE8:
                    $bytecode[$i] = chr(0x57);
                    break;

                /*E4 + dup2*/
                case 0xE4:
                    $bytecode[$i] = chr(0x5C);
                    break;

                /*E2 + dup_x1*/
                case 0xE2:
                    $bytecode[$i] = chr(0x5A);
                    break; 

                /*E5 + pop */
                case 0xE5:
                    $bytecode[$i] = chr(0x59);
                    break;

                /*EC,EE + getstatic*/
                case 0xEC: case 0xEE:
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($fix_index[$index] >> 8), chr($fix_index[$index] & 0xFF)];
                    $bytecode[$i] = chr(0xB2);
                    $i+=2;
                    break;

                case 0x14:
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($n_fix_index[$index]))
                        error('N Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($n_fix_index[$index] >> 8), chr($n_fix_index[$index] & 0xFF)];
                    $i+=2;  
                    break;  

                case 0x13:
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($n_fix_index[$index]))
                        error('N Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($n_fix_index[$index] >> 8), chr($n_fix_index[$index] & 0xFF)];
                    $i+=2;
                    break;

                /*FE,CC + ldc_w*/
                case 0xFE: case 0xCC:
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($fix_index[$index] >> 8), chr($fix_index[$index] & 0xFF)];
                    $bytecode[$i] = chr(0x13);
                    $i+=2;
                    break;

                /*EA,EB,ED + getstatic*/
                case 0xEA: case 0xEB: case 0xED:
                    $bytecode[$i] = chr(0xB2);
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($fix_index[$index] >> 8), chr($fix_index[$index] & 0xFF)];
                    $i+=2;
                    break;

                case 0x12:
                    $index = (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($n_fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1]));
                    $bytecode[$i+1] = chr($n_fix_index[$index] & 0xFF);
                    $i++;
                    break;

                /*FF + ldc*/
                case 0xFF:
                    $bytecode[$i] = chr(0x12);
                    $index = (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1]));
                    $bytecode[$i+1] = chr($fix_index[$index] & 0xFF);
                    $i++;
                    break;

                /*EF,F2,F3 + putstatic*/
                /*F1 +- putstatic*/
                /*F0 +- putstatic*/
                case 0xEF: case 0xF0: case 0xF1: case 0xF2: case 0xF3: 
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($fix_index[$index] >> 8), chr($fix_index[$index] & 0xFF)];
                    $bytecode[$i] = chr(0xB3);
                    $i+=2;
                    break;

                /*D8 + nop*/
                case 0xD8:
                    $bytecode[$i] = chr(0x00);
                    $bytecode[$i+1] = chr(0x00); 
                    $i+=1;
                    break;

                /*wo arg*/
                case 0x00: case 0x01: case 0x02: case 0x03: case 0x04:
                case 0x05: case 0x06: case 0x07: case 0x08: case 0x09:
                case 0x0A: case 0x0B: case 0x0C: case 0x0D: case 0x0E:
                case 0x0F: case 0x1A: case 0x1B: case 0x1C: case 0x1D:
                case 0x1E: case 0x1F: case 0x20: case 0x21: case 0x22:
                case 0x23: case 0x24: case 0x25: case 0x26: case 0x27:
                case 0x28: case 0x29: case 0x2A: case 0x2B: case 0x2C:
                case 0x2D: case 0x2E: case 0x2F: case 0x30: case 0x31:
                case 0x32: case 0x33: case 0x34: case 0x35: case 0x3B:
                case 0x3C: case 0x3D: case 0x3E: case 0x3F: case 0x40:
                case 0x41: case 0x42: case 0x43: case 0x44: case 0x45:
                case 0x46: case 0x47: case 0x48: case 0x49: case 0x4A:
                case 0x4B: case 0x4C: case 0x4D: case 0x4E: case 0x4F:
                case 0x50: case 0x51: case 0x52: case 0x53: case 0x54:
                case 0x55: case 0x56: case 0x57: case 0x58: case 0x59:
                case 0x5A: case 0x5E: case 0x60: case 0x61: case 0x62:
                case 0x63: case 0x64: case 0x65: case 0x66: case 0x67:
                case 0x68: case 0x69: case 0x6A: case 0x6B: case 0x6C:
                case 0x6D: case 0x6E: case 0x6F: case 0x70: case 0x71:
                case 0x72: case 0x74: case 0x75: case 0x76: case 0x77:
                case 0x78: case 0x79: case 0x7A: case 0x7B: case 0x7C:
                case 0x7D: case 0x7E: case 0x7F: case 0x80: case 0x81:
                case 0x82: case 0x83: case 0x85: case 0x86: case 0x87:
                case 0x88: case 0x89: case 0x8A: case 0x8B: case 0x8D:
                case 0x8D: case 0x8E: case 0x8F: case 0x90: case 0x91:
                case 0x92: case 0x93: case 0x94: case 0x95: case 0x96:
                case 0x97: case 0x98: case 0xAC: case 0xAD: case 0xAE:
                case 0xAF: case 0xB0: case 0xB1: case 0xBE: case 0xBF:
                case 0xC2: case 0xC3: /**/case 0x5C: case 0x8C:
                    break;

                /*1 byte arg*/
                case 0x10: case 0x15: case 0x16: case 0x17: case 0x18:
                case 0x19: case 0x36: case 0x37: case 0x38: case 0x39:
                case 0x3A: case 0xA9: case 0xBC:
                    $i++;
                    break;

                /*3 byte arg*/
                case 0xc4:
                    $i+=3;
                    break;

                /*goto_w, jsr_w*/
                case 0xC8: case 0xC9:
                    list($bytecode[$i+1], $bytecode[$i+2], $bytecode[$i+3], $bytecode[$i+4]) = [$bytecode[$i+4], $bytecode[$i+3], $bytecode[$i+2], $bytecode[$i+1]];
                    $i+=4;
                    break;

                /*iinc*/
                case 0x84:
                    $i+=2;
                    break;

                /*тут не нужно фиксить индекс*/
                case 0x11: case 0x99: case 0x9A: case 0x9B: case 0x9C:
                case 0x9D: case 0x9E: case 0x9F: case 0xA0: case 0xA1:
                case 0xA2: case 0xA3: case 0xA4: case 0xA5: case 0xA6:
                case 0xA7: case 0xA8: case 0xC6: case 0xC7:
                    list($bytecode[$i+1], $bytecode[$i+2]) = [$bytecode[$i+2], $bytecode[$i+1]];
                    $i+=2;
                    break;                   

                /*2 byte arg*/
                case 0xB2: case 0xB3:
                case 0xB4: case 0xB5: case 0xB6: case 0xB7: case 0xB8:
                case 0xBB: case 0xBD: case 0xC0: case 0xC1: case 0xC5:
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($fix_index[$index] >> 8), chr($fix_index[$index] & 0xFF)];
                    $i+=2;
                    break;

                case 0xB9:
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($fix_index[$index] >> 8), chr($fix_index[$index] & 0xFF)];
                    $bytecode[$i+4] = chr(0);
                    $i+=4;
                    break;

                case 0xBA:
                    $index = (ord($bytecode[$i+2]) << 8) + (ord($bytecode[$i+1]) & 0xFF);
                    if(!isset($fix_index[$index]))
                        error('Index Error: '.bin2hex($bytecode[$i].$bytecode[$i+1].$bytecode[$i+2]));
                    list($bytecode[$i+1], $bytecode[$i+2]) = [chr($fix_index[$index] >> 8), chr($fix_index[$index] & 0xFF)];
                    $bytecode[$i+3] = chr(0);
                    $bytecode[$i+4] = chr(0);
                    $i+=4;
                    break;

                case 0xAA:
                    $padding = (($i+1) % 4);
                    $padding = ($padding == 0) ? 0 : (4 - $padding);

                    $i += $padding;
                    list($bytecode[$i+1], $bytecode[$i+2], $bytecode[$i+3], $bytecode[$i+4]) = [$bytecode[$i+4], $bytecode[$i+3], $bytecode[$i+2], $bytecode[$i+1]];
                    $i += 4;

                    list($bytecode[$i+1], $bytecode[$i+2], $bytecode[$i+3], $bytecode[$i+4]) = [$bytecode[$i+4], $bytecode[$i+3], $bytecode[$i+2], $bytecode[$i+1]];
                    $low = (ord($bytecode[$i+1]) << 24) + (ord($bytecode[$i+2]) << 16) + (ord($bytecode[$i+3]) << 8) + ord($bytecode[$i+4]);
                    $i += 4;

                    list($bytecode[$i+1], $bytecode[$i+2], $bytecode[$i+3], $bytecode[$i+4]) = [$bytecode[$i+4], $bytecode[$i+3], $bytecode[$i+2], $bytecode[$i+1]];
                    $high = (ord($bytecode[$i+1]) << 24) + (ord($bytecode[$i+2]) << 16) + (ord($bytecode[$i+3]) << 8) + ord($bytecode[$i+4]);
                    $i += 4;

                    for($j = $low; $j <= $high; $j++) {
                        list($bytecode[$i+1], $bytecode[$i+2], $bytecode[$i+3], $bytecode[$i+4]) = [$bytecode[$i+4], $bytecode[$i+3], $bytecode[$i+2], $bytecode[$i+1]];
                        $i += 4;
                    }             
                    break;

                case 0xAB:
                    $padding = (($i+1) % 4);
                    $padding = ($padding == 0) ? 0 : (4 - $padding);

                    $i += $padding;
                    list($bytecode[$i+1], $bytecode[$i+2], $bytecode[$i+3], $bytecode[$i+4]) = [$bytecode[$i+4], $bytecode[$i+3], $bytecode[$i+2], $bytecode[$i+1]];
                    $i += 4;

                    list($bytecode[$i+1], $bytecode[$i+2], $bytecode[$i+3], $bytecode[$i+4]) = [$bytecode[$i+4], $bytecode[$i+3], $bytecode[$i+2], $bytecode[$i+1]];
                    $n = (ord($bytecode[$i+1]) << 24) + (ord($bytecode[$i+2]) << 16) + (ord($bytecode[$i+3]) << 8) + ord($bytecode[$i+4]);
                    $i += 4;

                    for($j = 0; $j < $n * 2; $j++) {
                        list($bytecode[$i+1], $bytecode[$i+2], $bytecode[$i+3], $bytecode[$i+4]) = [$bytecode[$i+4], $bytecode[$i+3], $bytecode[$i+2], $bytecode[$i+1]];
                        $i += 4;
                    }             
                    break;
                
                default:
                    error('Something Wrong. Opcode: 0x'.dechex($opcode));
                    break;
            }
        }
        return $bytecode;
    }

    class DataInputStream {
        private $binData;
        private $order;
        private $size;

        public function __construct($filename, $order = false, $fromString = false) {
            $this->binData = '';
            $this->order = $order;
            if(!$fromString) {
                if(!file_exists($filename) || !is_file($filename))
                    error('File not exists ['.$filename.']');
                $data = file_get_contents($filename);
            } else {
                $data = $filename;
            }
            $this->size = strlen($data);
            $this->binData = $data;
        }

        public function seek() {
            return ($this->size - strlen($this->binData));
        }

        public function read($size) {
            if(strlen($this->binData) < $size) {
                error('End Of File ('.strlen($this->binData).' < '.($size).')');
            }
            $ret = substr($this->binData, 0, $size);
            $this->binData = substr($this->binData, $size);
            return $ret;
        }

        public function readWOSkip($size) {
            if(strlen($this->binData) < $size) {
                error('End Of File ('.strlen($this->binData).' < '.($size).')');
            }
            $ret = substr($this->binData, 0, $size);
            return $ret;
        }

        public function readByte() {
            if(strlen($this->binData) < 1) {
                error('End Of File ('.strlen($this->binData).' < 1)');
            }
            $byte = substr($this->binData, 0, 1);
            $this->binData = substr($this->binData, 1);
            return ord($byte);
        }

        public function readShort() {
            if(strlen($this->binData) < 2) {
                error('End Of File ('.strlen($this->binData).' < 2)');
            }
            $short = substr($this->binData, 0, 2);
            $this->binData = substr($this->binData, 2);
            if($this->order)
                $short = $short[1].$short[0];
            return (ord($short[0]) << 8) + (ord($short[1]) & 0xFF);
        }

        public function readInt() {
            if(strlen($this->binData) < 4) {
                error('End Of File ('.strlen($this->binData).' < 4)');
            }
            $int = substr($this->binData, 0, 4);
            $this->binData = substr($this->binData, 4);
            if($this->order)
                $int = $int[3].$int[2].$int[1].$int[0];
            return (ord($int[0]) << 24) + (ord($int[1]) << 16) + (ord($int[2]) << 8) + (ord($int[3]) & 0xFF);
        }

        public function readLong() {
            if(strlen($this->binData) < 8) {
                error('End Of File ('.strlen($this->binData).' < 8)');
            }
            $long = substr($this->binData, 0, 8);
            $this->binData = substr($this->binData, 8);
            if($this->order)
                $long = $long[7].$long[6].$long[5].$long[4].$long[3].$long[2].$long[1].$long[0];
            return (ord($long[0]) << 56) + (ord($long[1]) << 48) + (ord($long[2]) << 40) + (ord($long[3]) << 32) + (ord($long[4]) << 24) + (ord($long[5]) << 16) + (ord($long[6]) << 8) + (ord($long[7]) & 0xFF);
        }

        public function readUTF() {
            $size = $this->readShort();
            return utf8_decode($this->read($size));
        }

        public function eof() {
            return !$this->binData||(strlen($this->binData) === 0);
        }
    }

    function rglob($pattern, $flags = 0) {
        $files = glob($pattern, $flags); 
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, rglob($dir.'/'.basename($pattern), $flags));
        }
        return $files;
    }

    function error($s) {
        die($s);
    }
?>