<?php
// --- Nettoyage et sécurisation des noms de fichiers ---
function safeFilename($name) {
    // Remplace les caractères spéciaux par des underscores
    $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    return $clean;
}

function getDecodedParam($key) {
    return isset($_GET[$key]) ? basename(urldecode($_GET[$key])) : null;
}
?>
<?php
session_start();
include '../connexion.php';
include 'nav.php';

/* ───────────────────────────────────────────
   IDENTIFICATION CLIENT
──────────────────────────────────────────── */
if (isset($_GET['id'])) {
    $codeClient = $pdo->query("SELECT codeClient FROM rt WHERE id =".$_GET['id'])->fetchColumn();
    $_POST['codeClient'] = $codeClient;
    $_SESSION['user'] = trim($_POST['codeClient']);
}
if (isset($_GET['codeClient'])) {
    $_POST['codeClient'] = $_GET['codeClient'];
    $_SESSION['user'] = trim($_POST['codeClient']);
}
if (isset($_POST['codeClient'])) {
    $_SESSION['user'] = trim($_POST['codeClient']);
}
if (empty($_SESSION['user'])) exit;
$user = $_SESSION['user'];

/* ───────────────────────────────────────────
   DOSSIERS
──────────────────────────────────────────── */
$baseDir      = dirname(__DIR__) . "/Documents/" . $user . "/";
$techDir      = $baseDir . "technique/";
$techDirret   = $baseDir . "techniqueRet/";
$directionDir = $baseDir . "direction/";
$equipeDir    = $baseDir . "equipe/";
$notesFile    = $techDir . "notes.txt";

if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);
if (!is_dir($techDir)) mkdir($techDir, 0777, true);
if (!is_dir($techDirret)) mkdir($techDirret, 0777, true);
if (!is_dir($directionDir)) mkdir($directionDir, 0777, true);
if (!is_dir($equipeDir)) mkdir($equipeDir, 0777, true);

/* ───────────────────────────────────────────
   NOTES
──────────────────────────────────────────── */
$message = "";
if (isset($_POST['note'])) {
    $note = trim($_POST['note']);
    if ($note !== "") {
        file_put_contents($notesFile, "[".date("Y-m-d H:i:s")."] $note\n", FILE_APPEND);
        $message = "Note enregistrée !";
    }
}

/* ───────────────────────────────────────────
   MISE À JOUR OBSERVATION (AJAX)
──────────────────────────────────────────── */
if (isset($_POST['updateObs'])) {
    $sql = "UPDATE rt SET obsValidRT = :o WHERE codeClient = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':o'=>$_POST['obsValidRT'], ':id'=>$user]);
    echo json_encode(["success"=>true, "obs"=>$_POST['obsValidRT']]);
    exit;
}

/* ───────────────────────────────────────────
   SUPPRESSION DE FICHIER (FIX APOSTROPHES)
──────────────────────────────────────────── */
function safeGetFile($key) {
    return isset($_GET[$key]) ? basename(urldecode($_GET[$key])) : null;
}

if ($f = safeGetFile('delete'))      { unlink($techDir . $f);      $message="Fichier technique supprimé."; }
if ($f = safeGetFile('deleteret'))   { unlink($techDirret . $f);    $message="Fichier technique supprimé."; }
if ($f = safeGetFile('deleteDir'))   { unlink($directionDir . $f);  $message="Fichier direction supprimé."; }
if ($f = safeGetFile('deleteEquipe')){ unlink($equipeDir . $f);     $message="Fichier équipe supprimé."; }

/* ───────────────────────────────────────────
   UPLOAD NORMAL + NETTOYAGE NOMS
──────────────────────────────────────────── */
function cleanFileName($name) {
        // 1. Conversion des accents vers ASCII simple
    $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);

    // 2. Suppression des apostrophes classiques et typographiques
    $name = str_replace(["'", "’"], "", $name);

    // 3. Remplace les caractères non autorisés par des underscores
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);

    // 4. Suppression des underscores multiples éventuels
    $name = preg_replace('/_+/', '_', $name);

    // 5. Trim final propre
    return trim($name, '_');


}

function processUpload($files, $dir) {
    if (!empty($files['name'][0])) {
        foreach ($files['tmp_name'] as $i => $tmp) {
            $clean = cleanFileName($files['name'][$i]);
            move_uploaded_file($tmp, $dir . $clean);
        }
        return true;
    }
    return false;
}

if (processUpload($_FILES['files']     ?? [], $techDir))      $message = "Upload réussi dans Technique.";
if (processUpload($_FILES['filesret']  ?? [], $techDirret))   $message = "Upload réussi dans Technique Retour.";
if (processUpload($_FILES['filesDir']  ?? [], $directionDir)) $message = "Upload réussi dans Direction.";
if (processUpload($_FILES['filesEquipe'] ?? [], $equipeDir))  $message = "Upload réussi dans Équipe.";

/* ───────────────────────────────────────────
   FICHE CLIENT
──────────────────────────────────────────── */
$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM rt WHERE codeClient = ? OR id = ?");
$stmt->execute([$user, $id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) die("Client introuvable.");

/* ───────────────────────────────────────────
   MISE À JOUR OBSERVATION POST
──────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['obsValidRT'])) {
    $stmt = $pdo->prepare("UPDATE rt SET obsValidRT = :obsValidRT WHERE codeClient = :codeClient");
    $stmt->execute([':obsValidRT'=>$user, ':codeClient'=>$user]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fiche Client et Documents</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: Arial; padding: 20px; }
h1,h2 { text-align:center; margin-bottom:20px; }
#dropzone_files, #dropzone_filesret, #dropzone_filesDir, #dropzone_filesEquipe {
    width:95%; max-width:80%; margin:10px auto; padding:35px 20px;
    border:3px dashed #4CAF50; border-radius:10px; background:#f0fff0;
    font-size:18px; cursor:pointer; transition:.2s;
}
.dragover { background:#d5ffd5 !important; border-color:#2e8b2e !important; }
.files-container { display:flex; flex-wrap:wrap; justify-content:center; gap:12px; margin-top:20px; }
.file-box { width:44%; max-width:160px; background:#f7f7f7; border-radius:10px; padding:10px; position:relative; }
.file-box img { width:100%; height:130px; object-fit:cover; border-radius:6px; border:1px solid #ccc; }
.delete-btn { position:absolute; top:5px; right:8px; color:red; font-weight:bold; text-decoration:none; font-size:20px; }
.file-name { font-size:14px; margin-top:6px; word-wrap:break-word; }
.progress-container { width:100%; background-color:#ddd; height:20px; border-radius:10px; overflow:hidden; margin:20px 0; }
.progress-bar { height:100%; width:0%; background-color:#4CAF50; border-radius:10px; transition: width 0.4s ease; }
#currentStepName { font-weight:bold; font-size:18px; margin-bottom:20px; }
</style>
</head>
<body>
<div class="container">
<a href="index.php" class="btn btn-secondary mb-3" style="position:fixed; top:20px; left:20px; z-index:1000;">&larr; Retour</a>
<h1>Fiche Client : <?= htmlspecialchars($client['nomClient'].' '.$client['prenomClient']) ?></h1>

<div class="progress-container"><div class="progress-bar" id="progressBar"></div></div>
<p id="currentStepName"></p>

<div class="card p-3 mb-4">
<p><b>Magasin :</b> <?= $client['magasin'] ?></p>
<p><b>Code Client :</b> <?= $client['codeClient'] ?></p>
<p><b>Adresse :</b> <?= $client['adresseClient'] ?> <?= $client['complAdress'] ?></p>
<p><b>Téléphone :</b> <?= $client['numTelClient'] ?></p>
<hr>
<h3>État d’avancement du chantier</h3>
<ul>
<li><b>Date demande RT :</b> <?= date('d/m/Y',strtotime($client['dateDemandeRT'])) ?></li>
<li><b>Observation de Demande RDV RT : </b> <?= $client['ObsDemRT'] ?></li>
<br>
<li><b>Date RDV RT :</b> <?= date('d/m/Y',strtotime($client['dateRDVRT'])) ?> de : <?= $client['HeurDRDVRT'] ?> à <?= $client['HeurFRDVRT'] ?> </li>
<li><b>Observation Validation RT : </b> <?= $client['obsValidRT'] ?></li>
<li><b>Observation de Retoure RDV RT : </b> <?= $client['ObsRetRT'] ?></li>
<li><b>Observation de Retour BM : </b> <?= $client['ObsRetBM'] ?></li>
<br>
<li><b>Date Envoi de Devis :</b> <?= date('d/m/Y',strtotime($client['dateDevisEnvoi'])) ?></li>
<br>
<li><b>Date RDV de la Pose :</b> <?= date('d/m/Y',strtotime($client['dateRdvPose'])) ?></li>
<li><b>Observation de RDV Pose : </b> <?= $client['ObsRdvPose'] ?></li>
<br>
<li><b>Date RDV SAV :</b> <?= date('d/m/Y',strtotime($client['dateRdvSAV1'])) ?></li>
<li><b>Observation de RDV SAV : </b> <?= $client['ObsRdvSAV1'] ?></li>
</ul>
</div>

<?php 
function renderDropzone($dir, $inputName, $deleteParam, $title, $user, $userDir){
    echo "<hr><h2>Partie $title </h2>";
    echo "<div id='dropzone_$inputName'>Glissez vos fichiers ici<br>ou cliquez pour sélectionner.</div>";
    echo "<input type='file' id='fileInput_$inputName' name='{$inputName}[]' multiple style='display:none;'>";
    echo "<div class='files-container'>";

    $files = array_diff(scandir($dir), ['.','..']);
    if (empty($files)) {
        echo "<p>Aucun fichier.</p>";
    } else {
        foreach ($files as $f) {
            $fileUrl = urlencode($f);
            $path = "../Documents/$user/$userDir/".$f;

            if ($userDir=="equipe")         $delet = "deleteEquipe";
            if ($userDir=="direction")      $delet = "deleteDir";
            if ($userDir=="techniqueRet")   $delet = "deleteret";
            if ($userDir=="technique")      $delet = "delete";

            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $imgExt=["jpg","jpeg","png","gif","webp"];

            if (in_array($ext, $imgExt)) $thumb = "<img src='$path'>";
            else {
                if ($ext=="pdf") $icon="pdf.png";
                elseif (in_array($ext,["xls","xlsm","xlsx","csv"])) $icon="excel.png";
                elseif (in_array($ext,["doc","docx"])) $icon="word.png";
                elseif (in_array($ext,["zip","rar","7z"])) $icon="zip.png";
                else $icon="file.png";
                $thumb = "<img src='icons/$icon' style='object-fit:contain;'>";
            }

            echo "<div class='file-box'>
                <a class='delete-btn' href='?$delet=$fileUrl' onclick=\"return confirm('Supprimer ?');\">×</a>
                <a href='".htmlspecialchars($path)."' target='_blank'>$thumb</a>
                <div class='file-name'><a href='".htmlspecialchars($path)."' target='_blank'>$f</a></div>
            </div>";
        }
    }
    echo "</div>";
}

renderDropzone($techDir,'files','delete','Documents techniques',$user,'technique');
renderDropzone($techDirret,'filesret','deleteret','Documents techniques Retour RT',$user,'techniqueRet');
renderDropzone($directionDir,'filesDir','deleteDir','Documents direction',$user,'direction');
renderDropzone($equipeDir,'filesEquipe','deleteEquipe','Documents équipe',$user,'equipe');
?>

<hr>
<form method="POST" class="card p-4 shadow mb-4">
<label class="form-label">Observation</label>

<!-- ################################################################################################################ -->
<!-- ###############################      code pour champt de texte observation   ################################### -->
<!-- ################################################################################################################ -->
<style>
    /* Masque l'avertissement de licence */
    .cke_notification_warning,
    [role="alert"] {
        display: none !important;
    }
</style>

<!-- Remplace juste la version LTS par 4.22.1 -->
<textarea name="obsValidRT" id="obsValidRT" class="form-control" rows="10"><?= $client['obsValidRT'] ?></textarea>

<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
    CKEDITOR.replace('obsValidRT', {
        language: 'fr',
        height: 300
    });
</script>

<!-- ################################################################################################################ -->
<!-- ################################################################################################################ -->

<br>
<input type="hidden" name="updateObs" >
<button type="submit" class="btn btn-warning w-100">Mettre à jour</button>
</form>

<script>
function initDropzone(dropId,inputId,field){
    let dz=document.getElementById(dropId),fi=document.getElementById(inputId);
    dz.onclick=()=>fi.click();
    ["dragenter","dragover","dragleave","drop"].forEach(ev=>dz.addEventListener(ev,e=>e.preventDefault()));
    dz.addEventListener("dragover",()=>dz.classList.add("dragover"));
    dz.addEventListener("dragleave",()=>dz.classList.remove("dragover"));
    dz.addEventListener("drop", e=>{ dz.classList.remove("dragover"); uploadFiles(e.dataTransfer.files,field); });
    fi.addEventListener("change",()=>uploadFiles(fi.files,field));
}
function uploadFiles(files,field){
    let fd=new FormData();
    for(let f of files) fd.append(field+'[]',f);
    fetch("",{method:"POST",body:fd}).then(()=>location.reload()).catch(err=>alert("Erreur upload: "+err));
}

initDropzone('dropzone_files','fileInput_files','files');
initDropzone('dropzone_filesret','fileInput_filesret','filesret');
initDropzone('dropzone_filesDir','fileInput_filesDir','filesDir');
initDropzone('dropzone_filesEquipe','fileInput_filesEquipe','filesEquipe');
</script>

<script>
// Progression
<?php
/*
 dateRDVRT is null or dateRDVRT < '2000-01-01'
 dateRDVRT > '2000-01-01' and validRetRT is null

 dateRDVRT IS NOT NULL AND dateRDVRT > '2000-01-01' and validRetRT =1  and (BMPeint =1 or BMPeint is null)  and (BMElect =1 or BMElect is null)  and (BMSol =1 or BMSol is null)  and  valideEnvDossRetRt is null
 validRetRT = 1 and (BMPeint =0 or BMElect = 0 or BMSol = 0) 
 validRetRT = 1 and ((BMPeint =1 or BMPeint is null) and (BMElect = 1 or BMElect is null) and (BMSol = 1 or BMSol is null))  and valideEnvDossRetRt is null
valideEnvDossRetRt = 1 and validDevisEnvoi is null
validDevisEnvoi = 1 and accepte is null
validDevisEnvoiAt = 1 and accepte is null
accepte='oui' AND dateRdvPose IS NULL
dateRdvPose > '2000-01-01' AND valideBonRecept IS NULL
valideBonRecept =1 AND validEntoiFact IS NULL
valideBonReceptSAV1 =1 and validEnvoiFactSAV1 is null
validEntoiFact =1
validEnvoiFactSAV1 =1
reqSAV1 =1 and dateRdvSAV1 is null //SELECT * FROM rt WHERE reqSAV1 = 1 AND (dateRdvSAV1 IS NULL OR dateRdvSAV1 < '2000-01-01') ORDER BY dateOrdrSAV ASC
dateRdvSAV1 > '2000-01-01' and dateRdvSAVValid is null
dateRdvSAVValid > '2000-01-01' AND valideBonReceptSAV1 IS NULL

*/
$etap=0;
if(empty($client['dateRDVRT']) ) $etap=0; // dateRDVRT IS NULL OR dateRDVRT < '2000-01-01'
if(!empty($client['dateRDVRT']) && empty($client['validRetRT'])) $etap=1;

if(!empty($client['valideEnvDossRetRt']) && empty($client['validDevisEnvoi'])) $etap=2;
if(!empty($client['validDevisEnvoi'])) $etap=3;


if(!empty($client['accepte'])) $etap=4;
if(!empty($client['dateRdvPose'])) $etap=5;

if((empty($client['dateRdvSAV1']))&&(!empty($client['reqSAV1']))) $etap=6;
if((!empty($client['dateRdvSAVValid']))&&(empty($client['dateRdvSAV1']))) $etap=8;
if((!empty($client['dateRdvSAV1']))&&(!empty($client['validEnvoiFactSAV1']))) $etap=7;

if((!empty($client['validEnvoiFactSAV1']))&&(empty($client['reqSAV1']))) $etap=9;
if((!empty($client['valideBonRecept']))&&(empty($client['reqSAV1']))) $etap=9; //!!!!!!!!

$etap= $client['etape'] ;
?>

const dataid = <?= json_encode($client['id']) ?>;
const steps=[
{name:"Dossier en attente de programmation de RT",color:"#3498db",url:"listeDemRT.php#"+dataid},
{name:"Dossier en attente de Validation de RT",color:"#9b59b6",url:"listeRetourRT.php#"+dataid},
{name:"Retour RT Valider attente Validation de Dossier",color:"#e67e22",url:"listeRetourRTAtt.php#"+dataid},
{},
{name:"etap 4 Dossier Envoyer et Valide Attente de Création de Devis",color:"#f1c40f",url:"listeDevisAEnvoi.php#"+dataid},
{name:"etap 5 Devis Envoyer Attente la Validation de Devis",color:"#2ecc71",url:"listeDevisAEnvoiAtt.php#"+dataid},

{name:"etap 6 Devis Accepter Attente de la date RDV de la Dépose",color:"#3498db",url:"listeRdvPose.php#"+dataid},
{},
{name: <?= json_encode("etap 8 Date de Pose Programmer pour le : ".$client['dateRdvPose']) ?>,color:"#9b59b6",url:"listeBonRec.php#"+dataid},

{name:"etap 9 Facture Envoyée",color:"#f1c40f",url:"listeFacture.php#"+dataid},
{name:"etap 10 Livraison Dossier Cloturé",color:"#2ecc71",url:"listeFactureValid.php#"+dataid},

{name:"etap 11 Attente Disponibilite LM",color:"#e67e22",url:"listeSAVattLM.php#"+dataid},
{name:"etap 12 Disponibilite LM Ok- Att Date RDV",color:"#bd5c07ff",url:"listeSAV.php#"+dataid},
{name:"etap 13 Chantier en SAV Att Bon de reception",color:"#a54d00ff",url:"listeBonRecSAV.php#"+dataid},

];

let currentStep=<?= $etap ?>;
function updateProgress(){
let progressBar=document.getElementById("progressBar");
let stepName=document.getElementById("currentStepName");
let percent=(currentStep/(steps.length-1))*100;
progressBar.style.width=percent+"%";
progressBar.style.backgroundColor=steps[currentStep].color;
stepName.innerHTML='Étape actuelle : <a href="'+steps[currentStep].url+'">'+steps[currentStep].name+'</a>';
}
updateProgress();
</script>


</div>
</body>
</html> 
