MONARC client project
=====================

Objective
---------

The backend part of the FrontOffice tool. It provides all the api endpoints to server the frontend calls with the json data.
The export/import functionality as wells as statistics aggregation is the other side of the functionality of the project. 
It is dependent on the zm-core projects. 


Middleware
----------

There is a AnrValidationMiddleware that is processed before the controllers actions and performs the anr access validation and some related endpoints access.
In case if the middleware validations passed successfully the anr object is added to the attribute of the request and can be accessible across all the /client-anr based controllers/actions. 

License
-------

This software is licensed under [GNU Affero General Public License version 3](http://www.gnu.org/licenses/agpl-3.0.html)

- Copyright (C) 2022-2024 Luxembourg House of Cybersecurity https://lhc.lu
- Copyright (C) 2016-2022 SMILE gie securitymadein.lu
- Copyright (C) 2016-2024 Jérôme Lombardi - https://github.com/jerolomb
- Copyright (C) 2016-2024 Juan Rocha - https://github.com/jfrocha
- Copyright (C) 2017-2024 Cédric Bonhomme - https://www.cedricbonhomme.org
- Copyright (C) 2019-2024 Ruslan Baidan - https://github.com/ruslanbaidan
