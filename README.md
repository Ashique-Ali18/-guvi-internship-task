\# GUVI Internship Task – Register → Login → Profile



\## Tech Stack (as required)

\- Frontend: HTML, CSS, JavaScript, Bootstrap

\- AJAX: jQuery (AJAX only, no form submission)

\- Backend: PHP

\- Auth DB: MySQL (prepared statements + password hashing)

\- Profile DB: MongoDB

\- Session store: Redis

\- Browser session: localStorage only (NO PHP sessions)



\## Folder Structure

/assets

&nbsp; /css

&nbsp; /js

&nbsp; /php

index.html

register.html

login.html

profile.html



\## Features

\- Register user (MySQL)

\- Login user (MySQL verify → Redis session → token in localStorage)

\- Profile fetch/update (MongoDB)

\- Logout (Redis session deleted + localStorage cleared)



\## Hosted Link

http://51.20.41.248/developer-internship/index.html



\## How to Run (High Level)

\- Start Apache + PHP

\- Start MySQL and create `auth\_db` + `users` table

\- Start Redis

\- Start MongoDB

\- Open index.html and use the flow

