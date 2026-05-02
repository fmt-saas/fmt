### Sélection d'une copropriété

Un système permet la limitation des résultats aux objets liés à une copropriété spécifique.

Un Service Collection spécifique à FMT permet de limiter les recherches aux objets dont le champ condo_id (si présent) correspond à celui sélectionné pour l'utilisateur en cours.

Dans l'interface utilisateur, un composant permet la sélection (ou désélection) d'une copropriété spécifique (parmi les copropriétés liées à l'utilisateur en cours).

Au niveau du serveur, la limitation dépend de la valeur de la setting `fmt.organization.user.condo_id` pour l'utilisateur en cours (['user_id' => $user_id]).