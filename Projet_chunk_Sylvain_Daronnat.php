<?php

//**************************************
// Phase 0 : déclaration des variables de base
//**************************************

// Variable de résultat instancié vide avant le traitement du texte

$resultat = "";

// On récupère les variables possiblement modifiés par l'utilisateur, sinon elles sont déclarées comme NULL

$texte_a_chunker = isset($_POST['texte']) ? $_POST['texte'] : NULL;

$lexique = isset($_POST['lexique']) ? $_POST['lexique'] : NULL;

$regles = isset($_POST['regles']) ? $_POST['regles'] : NULL;

// On peut maintenant lancer le traitement si l'utilisateur clique sur le bouton "go"

if (isset($_POST['analyse_chunk']))
	
{

	// Début du calcul du temps d'exécution du script

	$timestamp_debut = microtime(true);

	//**********************************
	// Phase 1 : On tokénise le texte à traiter
	//**********************************
	
	// On tokénise le texte avec preg_split, on garde tous les éléments séparateurs

	$tokens = preg_split('/([\s.,?!:\"\'])/', $texte_a_chunker, -1, PREG_SPLIT_DELIM_CAPTURE  );
	
	// On enlève les espaces inutiles de l'array en question

	$espace_blanc = array(" ");

	$tokens_traitement = (array_diff($tokens,$espace_blanc));
	
	// On fait en sorte d'avoir une nouvelle liste traitée avec les valeurs qui se suivent
	
	$tokens_clean = array_values($tokens_traitement);
	
	// On retire le dernier Array qui est toujours vide
	
	array_pop($tokens_clean);
	
	//***************************************
	// Phase 2 : Traitement du lexique et des règles
	//***************************************
	
	// On lit ligne par ligne le contenu du champ "lexique"

	foreach(explode("\n", $lexique) as $ligne) 
	
	{
	
		// Si une ligne ne contient rien du tout on ne la traite pas
	
		if(!empty($ligne))
	
		{
		
			// On sépare les noms des catégories des éléments qu'elles contiennent
		
			$traitement_lexique1 = explode(":=", $ligne);
		
			// On sépare les différents éléments au sein de chaque catégorie
			
			$traitement_lexique2 = explode(" ", $traitement_lexique1[1]);

			// On rassemble le tout dans un nouveau tableau mulidimensionnel trimmé pour éviter d'y insérer les espaces inutiles
		
			foreach($traitement_lexique2 as $elements_du_lexique)
       
			{

				$lexique_clean[trim($traitement_lexique1[0])][]=trim($elements_du_lexique);
		
			}
		
		}
	
	}

	foreach(explode("\n", $regles) as $line) 
	
	{
	
	// Si une ligne ne contient rien du tout on ne la traite pas
	
		if(!empty($line))
	
		{
		
			// On sépare les règles de leurs définitions
		
			$traitement_regles1 = explode("=", $line);
		
			// On insert les règles et leurs définitions dans deux arrays différentes que l'on trim pour enlever les espaces blancs inutiles
			
			$regles_clean[0][]=trim($traitement_regles1[0]);

			$regles_clean[1][]=trim($traitement_regles1[1]);

		}

	}

	//****************************
	// Phase 3 : Analyse et traitement
	//****************************
	
	// Fonction de recherche d'un élément (valeur) dans un tableau multidimensionnel

	function cherche_valeur($token_to_search,$lexique_clean) 
				
	{
	
		if (array_key_exists($token_to_search,$lexique_clean) or in_array($token_to_search,$lexique_clean)) 
	
		{
		
			return true;
	
		} 
	
		else 
		
		{
			
			$return = false;
			
			foreach (array_values($lexique_clean) as $value) 
			
			{
				
				if (is_array($value) and !$return) 
				
				{
					
					$return = cherche_valeur($token_to_search,$value);
				
				}
			
			}
			
			return $return;
		}
	
	}
				
	// Fonction de recherche d'un élément (clé) dans un tableau multidimensionnel
	
	function cherche_cle($token_to_search,$lexique_clean) 
				
	{
		
		foreach($lexique_clean as $key=>$value) 
		
		{
			
			$current_key=$key;
			
			if($token_to_search===$value OR (is_array($value) && cherche_cle($token_to_search,$value) !== false)) 
			
			{
				
			return $current_key;
			
			}
		
		}
		
		return false;
	}
	
	// On traite tous les tokens, et on incrémente un compteur au fur et à mesure (compteur $to)

	for($to=0; $to<count($tokens_clean) && isset($tokens_clean[$to]); $to++) 
	
	{
		
		// idem pour les règles (compteur $reg)
		
		for($reg=0; $reg<count($regles_clean[0]); $reg++)
	
		{

			// On distingue les différentes conditions et actions en rapport avec une règle
		
			$conditions = explode("+", $regles_clean[0][$reg]);
		
			$actions = explode("+", $regles_clean[1][$reg]);

			// On redefinit le token à chercher dans les tableaux pour qu'il puisse être utilisable par une fonction
			
			$token_to_search = $tokens_clean[$to];
			
			// On regarde si le token existe dans le lexique
			
			$result = cherche_valeur($token_to_search,$lexique_clean);
				
			// On regarde quelle catégorie le token a si il est définit dans le lexique
			
			$result_key = cherche_cle($token_to_search,$lexique_clean);
				
			// On regarde à quelle condition le token fait référence si il possède une catégorie
			
			$result_action = cherche_cle($result_key,$conditions);
				

			// Si le token est : 1) dans le lexique 2) qu'il a une catégorie 3) que sa catégorie remplit une des conditions des règles
			
			// On l'affiche en résultat précédé par l'action rattachée à sa règle
			
			if($result_action) 
				
			{
				
				$resultat = $resultat."]\n".$actions[0];

			}

		}
		
		// Si le token n'est pas dans le lexique on le concatène au précédent
		
		$resultat = $resultat." ".$tokens_clean[$to];
			
	}
	
	//ajout de la balise du dernière token qui est manquante
	
	$resultat = $resultat."]";
	
	// Fin du calcul du temps d'exécution du script

	$timestamp_fin = microtime(true);

	// différence en millisecondes entre le début et la fin

	$difference_ms = $timestamp_fin - $timestamp_debut;

	// Affichage du temps d'exécution dans la zone d'affichage des résultats ainsi que de l'entête des résultats

	$resultat = "Analyse effectuée en $difference_ms seconde(s).\n\n[****RESULTAT DU PARSING PAR CHUNK****".$resultat;
	
		//**********AIDE VISUELLE SI BESOIN (print_r et var_dump)**********//
	
	// print "<p><br>TOKENISATION :</b></p>";
		// print_r($tokens_clean);
	// print "<p><br>LEXIQUE:</b></p>";
		// var_dump($lexique_clean);
	// print "<p><br>REGLES :</b></p>";
		// var_dump($regles_clean);
	// print "<p><br>CONDITIONS(exemple) :</b></p>";
		// var_dump($conditions);
	// print "<p><br>ACTIONS(exemple) :</b></p>";
		// var_dump($actions);
		
}

// variables contenant les règles, le lexique et le texte à chunker (par défaut avant modification(s) par l'utilisateur)

$texte_a_chunker ="
Le Monde. Déjà, dans son deuxième film 21 grammes (2003), Alejandro Iñárritu s'intéressait à la mort. A cet intervalle, Quand un homme n'est plus tout à fait vivant pas encore mort. Lorsque les 21 grammes, poids supposé de son âme, s'envolent et que seule demeure, cette élévation à filmer. Le réalisateur mexicain pourrait l'attester. Sur ce gros plan, Leonardo DiCaprio pèse, Seulement 21 grammes. Il a perdu son enveloppe corporelle, ce meurtre répondait à un, Contrat tacite. « Je lui avais expliqué avant le tournage : “ Léo, nous allons tourner au, Canada par moins 40 degrés. Tu connais la phrase d'Ernest Shackleton, le pionnier de, l'exploration en Antarctique, à son équipe avant sa traversée du Pôle : \"A partir d'ici, nous n'allons sans doute plus jamais pouvoir revenir chez nous\". ";

$regles = "?+Det=[N--->
?+Adv=[Adv--->
?+Prep=[Prep--->
?+Pro=[N--->
?+ProPers=[V--->
?+Conj=[Conj--->
?+ArtDef=[V/N--->
?+Neg=[Neg--->
?+NUM=[Num--->
?+N_pro=[NAME--->
?+PCTNF=[PCTNF--->
?+PCTF=[PCTF--->";

$lexique = "Det:=Le le son la La les son sa
Adv:=déjà Déjà plus tout pas encore seulement Seulement moins
Prep:=dans à A de sur Sur avant au par du sans jamais chez
Pro:=cet cette ce un
ArtDef:=l' l L' L
ProPers:=Il il je Je lui nous Tu tu s' s
Conj:=quand Quand lorsque et que
Neg:=n' n
NUM:=deuxième 21 2003 40
N_pro:=Alejandro Leonardo
PCTNF:=,
PCTF:=! . ( ) ; / \ [ ] ’";

$commentaires = "- Les règles doivent être de la forme suivante : ? + CLASSE = [TYPE_CHUNK---> (par exemple).
- Utiliser le lexique de token : avant l'élément ':=' doit se trouver le nom de la catégorie grammaticale et après ':=' doit se trouver les tokens que cette catégorie comporte.
- La classe ? correspond à toute les catégories possibles.
- Les classes doivent être définies avant d'être utilisées.
- /!\ Ce chunker est sensible à la casse.";
	
?>

<html lang="fr"> 
<head>
<meta charset="UTF-8"/>
<title> Analyse par chunk - Sylvain Daronnat - M1 IdL</title>
<link rel="stylesheet" type="text/css" href="style.css">
</head>

<body>

<div id="titre">
	<h1> Projet - Formalisme pour le TAL</h1>
	<h2> Analyse par chunk à base de règles</h2>
	<h3> Par Sylvain Daronnat - M1 IdL </h3>
</div>
	
<div id="contenu">
	<center>
		<form method="post" action="Projet_chunk_Sylvain_Daronnat.php">
			<table border="1">
				<tr>
					<th colspan="4">Chunker à base de règles <a href="Projet_chunk_Sylvain_Daronnat.php">[RESET]</a></th>
				</tr>
				<tr>
					<th align="center">Commentaires</th>
					<th align="center">Texte à analyser</th>
					<td rowspan="2"><input value="Go" id="bouton" type="submit" name="analyse_chunk"></td>
					<th align="center">Résultat</th>
				</tr>
				<tr>
					<td>
						<textarea class="comlexreg" name ="com"><?=$commentaires?></textarea>
						<p align="center"><b>Lexique</b></p>
						<textarea class="comlexreg" name ="lexique"><?=$lexique?></textarea>
						<p align="center"><b>Règles</b></p>
						<textarea class="comlexreg" name ="regles"><?=$regles?></textarea>
					</td>
					<td> 
						<textarea  id="texte"  name="texte" valign="middle"><?=$texte_a_chunker?></textarea> 
					</td>
					<td>
						<textarea id="resultat" name="resultat" readonly="readonly"><?=$resultat?></textarea> 
					</td>
				</tr>
				<tr>
			<!--  <td colspan="4">
						<input name="graph" type="checkbox">afficher les statistiques d'utilisation des règles
					</td>  -->
				</tr>
			</table>
		</form>
	</center>		
</div>

<footer id="footer">

	<p> M1 IdL Université Grenoble Alpes <br>
	Projet dans le cadre du cours de formalisme pour le TAL<br>
	Enseignant : M. Lebarbé Thomas<br>
	Projet réalisé par Sylvain Daronnat<br>
	Conçu et testé sous <b>Mozilla Firefox</b>, optimisé pour un affichage en <b>1920*1080</b><br>
	<a href="#titre"> Revenir en haut de la page </a> </p>

</footer>
</body>

</html>