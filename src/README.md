# CREATION PROJET
https://start.spring.io
- Maven, Java, version 21, Group com.cpe, Artifact livedemo2, package_Name com.cpe.livedemo2,
- Dependances : Spring Web, Spring Data JPA (BDD), H2 Database (Connecteur vers la bdd H2 et démarre auto une bdd)

## 🏗️ Architecture

Architecture en 3 couches :

1. User Interface (frontend JS/HTML fourni par le professeur)
2. Services Metier (Spring Boot)
3. Persistance (BDD relationnelle, avec entites JPA)

FRONTEND : Navigateur (HTML/CSS/JS)
   |
   |__Requête HTTP/JSON---> BACKEND : Web Server (Spring Boot)
           
### Architecture du BACKEND (Spring Boot)
`/rest` : UserController, CardController, ?
  -> `/service` : UserService, CardService, ?
        -> `/repository` : UserRepository, CardRepository, ?
                -> `/model` : User, Card, ?   ==> `@Entity` JPA
                        -> BDD H2 (en mémoire, auto démarrée par Spring Boot) ==> `user`, `card`, ? 
                        


Les **repository** sont des interfaces qui étendent `JpaRepository` et `CrudRepository` et permettent d'effectuer des opérations CRUD sur les entités.
Ils travaillent avec les `@Entity` définies dans le package `model` et sont utilisés par les **services** pour accéder aux données de la BDD.
