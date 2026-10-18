<?php
require_once __DIR__.'/includes/learning-tools.php';
$id=max(0,(int)($_GET['id']??0)); $lesson=null; $items=[];
try { $lesson=$lessonModel->find($id); if($lesson && (int)$lesson['is_active']===1) $items=$lessonModel->getItems($id); else $lesson=null; }
catch(Throwable $e){ error_log('[lesson] '.$e->getMessage()); }
if(!$lesson){http_response_code(404);learn_header('Lección no disponible','No se encontró una lección activa.');echo '<section class="learn-panel"><p class="learn-muted">Puede que el contenido no esté cargado o que el enlace sea antiguo.</p><a class="learn-button" href="lessons.php">Volver a lecciones</a></section>';learn_footer();exit;}
if(!$items){learn_header($lesson['title'],'');echo '<section class="learn-panel"><h2>Esta lección aún no tiene preguntas</h2><p class="learn-muted">La lección está registrada, pero necesita elementos en lesson_items.</p><a class="learn-button" href="lessons.php">Volver</a></section>';learn_footer();exit;}
// Each attempt uses a server-stored list of question IDs so the browser cannot alter the grading denominator.
shuffle($items);$items=array_slice($items,0, min(10,count($items)));
$attempt=bin2hex(random_bytes(16)); $_SESSION['lesson_quiz'][$attempt]=['lesson_id'=>$id,'item_ids'=>array_map(fn($x)=>(int)$x['id'],$items),'started_at'=>time()];
if(count($_SESSION['lesson_quiz'])>8){$_SESSION['lesson_quiz']=array_slice($_SESSION['lesson_quiz'],-8,null,true);}
try{$progressModel->start($learnUserId,$id);}catch(Throwable $e){error_log('[lesson start] '.$e->getMessage());}
learn_header($lesson['title'],$lesson['description']??'');
echo '<form method="post" action="lesson-result.php" class="learn-panel learn-form">'.learn_csrf().'<input type="hidden" name="attempt" value="'.learn_h($attempt).'">';
foreach($items as $i=>$it){$answer=(string)$it['item_value'];$pool=[$answer];foreach($items as $other){if((string)$other['id']!==(string)$it['id']&&!in_array((string)$other['item_value'],$pool,true)&&count($pool)<4)$pool[]=(string)$other['item_value'];}while(count($pool)<4)$pool[]='Ninguna de las anteriores';shuffle($pool);$hint=trim((string)($it['hint']??''));if($hint==='')$hint='Selecciona el elemento que corresponde: '.($it['item_type']==='letter'?'letra':($it['item_type']==='phrase'?'frase':'palabra')).'.';echo '<fieldset class="learn-question"><legend><b>Pregunta '.($i+1).'</b></legend><h3>'.learn_h($hint).'</h3><div class="learn-options">';foreach($pool as $opt)echo '<label class="learn-option"><input type="radio" required name="answers['.(int)$it['id'].']" value="'.learn_h($opt).'"> <span>'.learn_h($opt).'</span></label>';echo '</div></fieldset>';}
echo '<button class="learn-button" type="submit">Enviar respuestas →</button></form>';learn_footer();
