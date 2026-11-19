<?php
declare(strict_types=1);
/** OCR LOCAL. Requiere Tesseract; en PDF usa la capa de texto o Poppler + Tesseract. */
function commandText(array $cmd): array {
    $io=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $proc=@proc_open($cmd,$io,$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($proc)) return [false,'','No se pudo iniciar '.($cmd[0]??'comando')];
    fclose($pipes[0]);
    $out=stream_get_contents($pipes[1],2000000);fclose($pipes[1]);
    $err=stream_get_contents($pipes[2],500000);fclose($pipes[2]);
    $status=proc_close($proc);
    return [$status===0,(string)$out,(string)$err];
}
function ocrFile(string $file,string $mime): array {
    global $config;
    $tess=(string)($config['ocr_tesseract']??'tesseract');
    $langs=(string)($config['ocr_languages']??'spa+eng');
    check((bool)preg_match('/^[a-zA-Z_+]+$/D',$langs),'Idiomas OCR inválidos en configuración.');
    if($mime==='application/pdf') {
        [$ok,$text]=commandText(['pdftotext','-layout',$file,'-']);
        if($ok && mbLen(trim($text))>=50) return ['texto'=>substr($text,0,100000),'estado'=>'procesado','alertas'=>'Texto extraído de la capa digital del PDF (sin OCR de imagen).'];
        $prefix=sys_get_temp_dir().'/mpocr_'.bin2hex(random_bytes(12));
        $poppler=(string)($config['pdf_pdftoppm']??'pdftoppm');
        [$ok,$unused,$err]=commandText([$poppler,'-f','1','-l','3','-r','180','-png',$file,$prefix]);
        $pics=glob($prefix.'-*.png')?:[];
        $pieces=[];$warnings=[];
        foreach($pics as $pic){
            [$success,$out,$e]=commandText([$tess,$pic,'stdout','-l',$langs]);
            if($success) $pieces[]=$out;else $warnings[]=trim($e);
            @unlink($pic);
        }
        if(!$ok && !$pieces) return ['texto'=>'','estado'=>'no_disponible','alertas'=>'No fue posible procesar el PDF. Instalá Poppler (pdftoppm) y Tesseract. '.substr($err,0,200)];
        $raw=trim(implode("\n\n",$pieces));
        return ['texto'=>substr($raw,0,100000),'estado'=>$raw!==''?'procesado':'error',
          'alertas'=>$raw!==''?'OCR de las primeras 3 páginas. Revisar todos los importes y cantidades.':'No fue posible leer la imagen. '.implode(' ',$warnings)];
    }
    [$ok,$out,$err]=commandText([$tess,$file,'stdout','-l',$langs]);
    return ['texto'=>substr(trim($out),0,100000),'estado'=>$ok&&trim($out)!==''?'procesado':'no_disponible',
      'alertas'=>$ok?'Revisar y corregir los datos: la lectura OCR no es infalible.':'OCR no disponible. Instalá Tesseract + idioma spa. '.substr($err,0,200)];
}
function uploadInvoice(array $upload): array {
    global $config;
    check(($upload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK,'No se recibió un archivo válido.');
    check(is_uploaded_file((string)$upload['tmp_name']),'Carga no válida.');
    check((int)$upload['size']>0 && (int)$upload['size']<=(int)($config['max_upload_bytes']??10485760),'Archivo demasiado grande (máximo 10 MB).');
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file((string)$upload['tmp_name']);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
    check(isset($types[$mime]),'Admitimos PDF, JPG o PNG.');
    if($mime!=='application/pdf')check(@getimagesize((string)$upload['tmp_name'])!==false,'Imagen inválida.');
    $name=bin2hex(random_bytes(20)).'.'.$types[$mime];
    $dir=dirname(__DIR__).'/storage/facturas';
    if(!is_dir($dir)) check(mkdir($dir,0700,true),'No se pudo crear el almacén de facturas.');
    $dest=$dir.'/'.$name;
    check(move_uploaded_file((string)$upload['tmp_name'],$dest),'No se pudo guardar el documento.');
    @chmod($dest,0600);
    try { $result=ocrFile($dest,$mime); }
    catch(Throwable $e){error_log((string)$e);$result=['texto'=>'','estado'=>'error','alertas'=>'Falló la lectura OCR. Cargá los datos manualmente.'];}
    return ['archivo'=>$name,'hash'=>hash_file('sha256',$dest)]+$result;
}
function guessInvoice(string $text,array $ingredients,array $suppliers): array {
    $result=['proveedor'=>null,'numero'=>'','fecha_emision'=>'','total_detectado'=>'','lineas'=>[],'advertencias'=>[]];
    if($text==='') return $result;
    $norm=static function(string $s):string {return function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s);};
    $lower=$norm($text);
    foreach($suppliers as $p){
        if(mbLen((string)$p['nombre'])>=4 && str_contains($lower,$norm((string)$p['nombre']))){$result['proveedor']=$p['id'];break;}
    }
    if(preg_match('/(?:factura|comprobante)\s*(?:electr[oó]nica)?\s*(?:n[°ºo.]\s*)?(?:n[uú]mero\s*)?[:#-]?\s*([A-Z0-9-]{4,35})/iu',$text,$m)) $result['numero']=substr($m[1],0,100);
    if(preg_match('/\b(\d{1,2})[\/.-](\d{1,2})[\/.-](20\d{2})\b/',$text,$m)){
        $date=sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]);
        try{$result['fecha_emision']=safeDate($date);}catch(Throwable $ignore){}
    }
    if(preg_match('/(?:total\s*(?:a\s*pagar)?|importe\s*total)\s*[:$ ]+\s*([\d.,]+)/iu',$text,$m)){
        $raw=preg_replace('/[^\d.,]/','',$m[1]);
        // OCR puede traer separadores ambiguos: dejar como texto sin interpretar.
        $result['total_detectado']=$raw;
    }
    foreach(preg_split('/\R/u',$text)?:[] as $line){
        $lineLower=$norm($line);
        foreach($ingredients as $ing){
            if(!preg_match('/(?<!\p{L})'.preg_quote((string)$ing['nombre'],'/').'(?!\p{L})/iu',$line)) continue;
            // Solo se sugieren renglones con cantidad y unidad explícitas.
            if(preg_match('/\b(\d{1,6}(?:[.,]\d{1,3})?)\s*(?:kg|kilos?|litros?|lts?\.?|uds?|unid(?:ades)?)\b/iu',$line,$m)){
                $qty=str_replace(',','.',$m[1]);$candidate='';
                // Tomar el último importe con dos decimales, si hay uno; NO guardar automáticamente.
                if(preg_match_all('/(?:\$\s*)?\b((?:\d{1,3}(?:\.\d{3})+|\d+)[,.]\d{2})\b/u',$line,$found)&&$found[1]){
                    $raw=end($found[1]);
                    if(str_contains($raw,',')&&str_contains($raw,'.')) $raw=str_replace(',','.',str_replace('.','',$raw));
                    else $raw=str_replace(',','.',$raw);
                    try{$candidate=amount($raw);}catch(Throwable $ignored){}
                }
                $result['lineas'][]=['ingrediente_id'=>$ing['id'],'cantidad'=>$qty,'costo_total'=>$candidate];
            }
            break;
        }
    }
    $result['advertencias'][]='La lectura automática es orientativa. Verificá proveedor, número, fecha, renglones y total antes de confirmar.';
    return $result;
}
