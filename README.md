## Database structure
```
gebruikers(id, gebruikers_naam, wachtwoord_hash, gemaakt_op)
auth_tokens(gebruiker_id, token_hash, gemaakt_op, verloopt_op)

huishoudens(id, naam, omschrijving, foto)
kamers(id, naam, huishouden_id, foto, breedte, hoogte)

voorwerpen(id, naam, omschrijving, huishouden_id, kamer_id, loc_x, loc_y, doos_id, foto, eigendomstype)
voorwerp_eigendom_rel(gebruiker_id, voorwerp_id)

labels(id, huishouden_id, naam)
gelabelde_voorwerpen_rel(label_id, voorwerp_id)

upload_tokens(token, huishouden_id, kamer_id, voorwerp_id, verloopt_op)
```