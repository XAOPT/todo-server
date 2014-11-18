Общий концепт (REST API)
=============

Примеры запросов и ресурсов:

GET task/\d+ - получить полное описание задачи с айдишником id.  
GET task/\d+/comment - получить все комментарии для задачи id.  
POST project/\d+/task - добавить новую задачу в проект с айдишником id.

Ошибки возвращаются через HTTP Response Codes. Стандартные коды:

200 - ОК  
400 - Bad Request (если даны неправельные параметры запроса)  
401 - Unauthorized (если не прошли авторизацию)  
403 - Forbidden (если нет нужных разрешений у юзера)  
405 - Invalid Method Call (возвращается автоматически если не реализован метод)  
500 - Internal Server Error (возвращается автоматически, когда есть ошибка PHP)  

GET - запрос на получение ресурса, обычно параметры передаются в $_GET, возвращает:  
200 - OK и описание ресурса как JSON в ответе.  
404 - Not Found

POST - запрос для создания нового ресурса. Возвращает:  
201 - OK (Created) и обычно JSON с айдишником созданного ресурса.

PUT - запрос на изменение существующего ресурса. Все новые данные передаются как JSON в теле запроса. Возвращает:  
200 - OK

DELETE - запрос на удаление ресурса. Используется редко, потому что обычно ресурсы только отмечаются как удаленные, чтобы не нарушать целостности базы данных.  
204 - (OK) No Content  
404 - Not Found


Роли и права
=============
Каждому юзеру назначается системная роль (с системными правами) и роль на каждом проекте (на разных проектах роль может быть своя).

Системные права:
* system.access
* administer - админские права перекрывают все остальные.
* project.management - управление проектами (создание и удаление)
* people.management - управление людьми (создание, смена роли, смена пароля)

Права по проекту:
* tester - тестеры могут менять статус чужой задачи на (reopened или closed);
* task.management - (создание, удаление и редактирование задач);
* comment.management - (редактирование и удаление чужих комментариев);

Права, которые есть по умолчанию:
* создавший задачу (автор) может делать с ней что угодно;
* создавший коммент, так же может делать с ним все, что можно;

Таким образом PUT task работает только для автора, либо для чувака с task.management. Еще PUT работает для тестера только на смену статуса задачи.

**GET role**  
Возвращает список всех ролей  
 ``` json
Response: 
{
    "class": "userRoleList",
    "items": [
        {
            "class": "role",
            "id": 1,
            "sysrole": true,
            "sysname": "admin",
            "name": "Администратор",
            "permissions": [
                "system.access",
                "administer"
            ]
        },
        ...
    ]
}
```

**POST role**  
_access: administer_  
Создаёт новую роль  
``` json
Request: {
  sysname,
  sysrole
}
Response: {
  id : 100
}
```

**PUT role/\d+**  
_access: administer_  
Изменение роли  
``` json
request: {
    "name": "someone",
    "permissions": ["system.access","people.management"]
}
```
Поля:  
permissions  
name

**DELETE role/\d+**  
_access: administer_  
Удаляет роль. Для всех пользователь, имеющих данную роль, значение роли устанавливается пустым.



Авторизация
=============

**POST auth**  
Авторизация с помощью почты и пароля.  
``` json
Request:
{
  "email": "",
  "pwd": ""
}
```
``` json
Response:
{
  "status": 0,
  "auth_token": "",
  "userid": 1
}
```

**POST auth/fb**  
Авторизация с помощью фейсбук-авторизации  
https://developers.facebook.com/docs/facebook-login/using-login-with-games  
``` json
Request:
{
  "accessToken": "",
  "signedRequest": "",
}
```
``` json
Response:
{
  "status": 0,
  "auth_token": "",
  "userid": 1
}
```



Юзеры
=============

**GET user**  
Список пользователей  
``` json
Response: {
    "from": 0,
    "count": 24,
    "items": []
}
```

**POST user**  
_access: administer, people.management_  
Создание пользователя
``` json
Request:
{
  "firstname": "",
  "lastname": "",
  "role": "",
  "email": ""
}
```

**PUT user/\d+**  
_access: administer, people.management_  
Изменение данных пользователя  
people.management не может устанавливать роль "admin", нельзя менять самому себе

Вообще people.management - читерский доступ. Можно нахоботить себе любой доступ кроме админского через фейковый аккаунт, например.
Поля для менеджера: 
email
firstname
lastname
deleted  

**PUT user/\d+/projectrole**  
_access: administer, people.management_  
Установить проектную роль.  
Если роль пустая - запись удаляется  
``` json
Request: {
  "project":
  "role":
}
```

Проекты
=============

**GET project**  
Возвращает список всех проектов  
``` json
Response: {
  "status" : 0
  "items" : [
    {}, // _project_object 
    {} // _project_object
  ]
}
```

**GET project/\d+**  
Возвращает описание проекта  
``` json
Response:
{
    "status" : 0,
    "item" : {
    "class" : "project",
    "id" : 1,
    "archived" : false,
    "title" : "",
    "shorttitle" : "",
    "tagcolor"   : "",
    "created"    : "" // created_unix
  }
}
```

**POST project**  
_access: administer, project.management_  
Добавить новый проект
``` json
Request: {
  "title": "",
  "shorttitle" : ""
}
```
``` json
Response: {
  "status" : 0,
  "id" : 0
}
```

**PUT project/\d+**  
access: administer, project.management  
Редактирование проекта
``` json
Request: {
  "title" : "",
  "shorttitle" : "",
  "tagcolor" : ""
```
``` json
Response: {
  "status" : 0,
  "id" : 0
}
```

Задачи
=============

**GET task**  
Query params:
``` json
id // айди задачи или несколько айди через запятую
from // с какой позиции вернуть
count // сколько вернуть
assignee
status
title
text
timesheet_from // что-то делалось по таску в диапазоне
timesheet_to
project
priority
```
Response:  
``` json
{
  "status": 0,
  "from" : 0,
  "count" : 100,
  "items" : [
      {
        "project"  : "", //айдишник проекта к которому относится таск. ~projid
        "type"     : "task, milestone, folder, issue, feature, testcase",  // (ENUM)
        "title"    : "My super task.",
        "priority" : 0, // (TINYINT)
        "status"   : "open, inprogress, finished, reopened, closed, canceled", // (ENUM)
        "assignee" : 10, // юзер, которому назначена задача. ~uid
        "parentTask" : 10, // айдишник родительской задачи или 0
        "sub-tasks" : [
            { "id" : 1000 },
            { "id" : 1001 }
        ],
        "attachments" : [  // Список вложений. Возвращается только при запросе конкретной задачи (есть id в параметрах)
              {
                  "url" : "",
                  "filename" : 1000,
                  "size" : 1000,
              }
        ],
        "estimatedEffortSeconds" : 3600,
        "startDate" : 0, // Unix timestamp
        "duration"  : 0, // целое число дней
        "deadline"  : 0, // Unix timestamp (date)
        "created" : 0, //Unix Timestamp
        "createdby" : 0
      },
      ...
  ]
}
```

**POST task**  
access: administer, task.management  
Добавить новую задачу в указанный проект  
``` json
Request: {
  "title": ""
}
```
``` json
Response: {
  "id" : 100
}
```


**POST task/\d+/attachment**  
_access: administer, task.management, assignee_  
Прикрепить файл к задаче


**PUT task/\d+**  
_access: administer, task.management_
Изменить свойства существующей задачи.
``` json
// Поля для people.management:
project
type
title
priority
status
assignee
parentTask
deadline
startDate
duration
estimatedEffortSeconds

// Поля для assignee:
status

// Поля для: access: tester
status
```

**DELETE task/\d+**  
_access: administer, task.management_  
Ответ: 204? No Content  
Удалять можно только задачи верхнего уровня (не имеющие дочек)  


**DELETE task/attachment**  
_access: administer, task.management, assignee_  
Удалить прикреплённый файл
``` json
Request: {
  "id": "" // id файла
}
```


**[_deprecated_] GET task/\d+/oldnote**  
Возвращает бинарник комментариев для задачи.  
_deprecated_

**[_deprecated_] GET task/\d+/oldcalendar**  
Возвращает бинарник календаря для задачи.  


Микротаски
=============
**PUT microtask/\d+**
{
    “state” : “open|finished|canceled”
    “text” : “string”
}

Комментарии
=============
Комментарии пишутся к задачам - для отображения дискуссии. Причем, само описание задачи - это автоматически созданный корневой комментарий, а все последующие комменты вкладываются в него.

**GET comment?taskid**
``` json
Response: {
    "status": 0,
    "taskid": 1,
    "items": [
        {
            "id": 0,
            "parentComment": 0,
            "taskid": 0,
            "text": "",
            "created": "",
            "createdby": 0,
            "modified": "",
            "modifiedby": 0
        }
    ]
}
```

**POST comment**  
_access: comment.management, assignee, tester_
``` json
Request: {
  "text": "",
  "parent": 100,
}
Response: {
  "id" : 100,
}
```

**PUT comment/\d+**  
_access: administer, comment.management, owner_

Здесь "text" - это форматированный HTML текст с простейшими тэгами.
При редактировании комментария у него автоматически апдейтятся поля кем и когда изменено.

Поля:
text

Таймшит (работа над задачей)
=============

Когда юзер вбивает часы отработанные по задаче, они сохраняются в отдельной таблице базы.

**GET timesheet?userid&taskid&dayfrom&dayto**
``` json
Response: {
  "status": 0,
  "items": [
    { 
      "day" : 15275, 
      "userid" : 22,
      "taskid" : 22,
      "worktimeSeconds" : 3600 
    }
  ]

}
```

**PUT timesheet**  
_access: administer, task.management, assignee_  
``` json
Request: {
  "day": 15000,
  "userid" : 22, // опционально
  "taskid" : 30,
  "worktimeSeconds" : 1000
}
```
Залогиненый юзер должен быть либо assignee задачи, либо менеджером с пермишном task.management

Если у юзера нет прав task.management, то userid не должен указываться, т.к. автоматически подразумевается assignee задачи.

Запись удаляется если worktimeSeconds = нулю.


Календари
=============
Календарь есть общий для всех сотрудников (где отмечаются праздники и отклонения от дефолтного календаря (каждая суббота и воскресенье - выходной)) и есть персональные календари, где отмечаются отклонения от общего календаря для каждого юзера в отдельности.

**GET calendar?from=15275&count=100&userid**  
Запрос на получение общего календаря за указанный интервал дат.
``` json
Response: { 
  "status" : 0, 
  "from" : 0,
  "count" : 100,
  "items" : [
    {
      "day": 15232,
      "userid": 0,
      "kind": "dayoff"
    }
  ]
}
```

**PUT calendar**  
access: administer  
Запрос на создание исключения из дефолтного календаря (userid не обязателен). 
``` json
Request: {
  "day": 15222,
  "userid": 0,
  "kind" : "workday|dayoff"
}
```

**DELETE calendar?day=15275&userid**  
access: administer, people.management  
Запрос на удаление исключения из общего календаря или из календаря юзера (если задан userid).


Конвертер
=============

**GET converter/project**
конвертирует таблицу project в project_todo

**GET converter/task**
конвертирует таблицу task в todo_task

**GET converter/user**
конвертирует таблицу user в todo_user
