	xajax.debug.text = [];
	xajax.debug.text[100] = 'ATTENTION : ';
	xajax.debug.text[101] = 'ERREUR : ';
	xajax.debug.text[102] = 'MESSAGE DE DEBUG XAJAX :\n';
	xajax.debug.text[103] = '...\n[R�PONSE LONGUE]\n...';
	xajax.debug.text[104] = 'ENVOI DE LA REQU�TE';
	xajax.debug.text[105] = 'ENVOY� [';
	xajax.debug.text[106] = ' octets]';
	xajax.debug.text[107] = 'APPEL : ';
	xajax.debug.text[108] = 'URI : ';
	xajax.debug.text[109] = 'INITIALISATION DE LA REQU�TE';
	xajax.debug.text[110] = 'TRAITEMENT DES PARAM�TRES [';
	xajax.debug.text[111] = ']';
	xajax.debug.text[112] = 'AUCUN PARAM�TRE � TRAITER';
	xajax.debug.text[113] = 'PR�PARATION DE LA REQU�TE';
	xajax.debug.text[114] = 'D�BUT DE L\'APPEL XAJAX (d�pr�ci�: utilisez plut�t xajax.request)';
	xajax.debug.text[115] = 'D�BUT DE LA REQU�TE';
	xajax.debug.text[116] = 'Aucun traitement disponible pour traiter la r�ponse du serveur.\n';
	xajax.debug.text[117] = '.\nV�rifie s\'il existe des messages d\'erreur du serveur.';
	xajax.debug.text[118] = 'RE�US [statut : ';
	xajax.debug.text[119] = ', taille: ';
	xajax.debug.text[120] = ' octets, temps: ';
	xajax.debug.text[121] = 'ms] :\n';
	xajax.debug.text[122] = 'Le serveur a retourn� la statut HTTP suivant : ';
	xajax.debug.text[123] = '\nRE�US :\n';
	xajax.debug.text[124] = 'Le serveur a indiqu� une redirection vers :<br />';
	xajax.debug.text[125] = 'FAIT [';
	xajax.debug.text[126] = 'ms]';
	xajax.debug.text[127] = 'INITIALISATION DE L\'OBJET REQU�TE';

	xajax.debug.exceptions = [];
	xajax.debug.exceptions[10001] = 'R�ponse XML non valide : La r�ponse contient une balise inconnue : {data}.';
	xajax.debug.exceptions[10002] = 'GetRequestObject : XMLHttpRequest n\'est pas disponible, xajax est d�sactiv�.';
	xajax.debug.exceptions[10003] = 'File pleine : Ne peut ajouter un objet � la file car elle est pleine.';
	xajax.debug.exceptions[10004] = 'R�ponse XML non valide : La r�ponse contient une balise ou un texte inattendu : {data}.';
	xajax.debug.exceptions[10005] = 'URI de la requ�te non valide : URI non valide ou manquante; auto-d�tection �chou�e; veuillez en sp�cifier une explicitement.';
	xajax.debug.exceptions[10006] = 'R�ponse de commande invalide : Commande de r�ponse re�ue mal form�e.';
	xajax.debug.exceptions[10007] = 'R�ponse de commande invalide : Commande [{data}] est inconnue.';
	xajax.debug.exceptions[10008] = 'L\'�l�ment d\'ID [{data}] est introuvable dans le document.';
	xajax.debug.exceptions[10009] = 'Requ�te invalide : Aucun nom de fonction indiqu� en param�tre.';
	xajax.debug.exceptions[10010] = 'Requ�te invalide : Aucun objet indiqu� en param�tre pour la fonction.';

	if ('undefined' != typeof xajax.config) {
		if ('undefined' != typeof xajax.config.status) {
			/*
				Object: mise � jour
			*/
			xajax.config.status.update = function() {
				return {
					onRequest: function() {
						window.status = 'Envoi de la requ�te...';
					},
					onWaiting: function() {
						window.status = 'Attente de la r�ponse...';
					},
					onProcessing: function() {
						window.status = 'En cours de traitement...';
					},
					onComplete: function() {
						window.status = 'Fait.';
					}
				}
			}
		}
	}