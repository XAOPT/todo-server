Общий концепт (REST API)
=============

Примеры запросов и ресурсов:

GET task/<id> - получить полное описание задачи с айдишником id.
GET task/<id>/comment - получить все комментарии для задачи id.
POST project/<id>/task - добавить новую задачу в проект с айдишником id.

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


Юзеры
=============

**GET user**
Список пользователей
<pre>
{
    from: 0,
    count: 24,
    items: [] // параметры, которые необходимо вернуть. Если отсутствуют - использовать дефолтный набор
}
</pre>

**GET user/_id**
Данные пользователя

**GET user/_id/clientSettings**
Возвращает настройки клиента пользователя

**GET user/_id/vars**
Возвращает доп. информацию о пользователе
_устарело_

**GET user/_id/avatar**
Возвращает ссылку на аватару юзера, чтобы скачать.
<pre>
{
    "url" : "http://office.divogames.ru/todo/avatars/1.jpg",
}
</pre>
_устарело_

**POST user**
_access: administer, people.management_
Создание пользователя
<pre>
Request:
{
  username: "",
  email: ""
}
</pre>

**PUT user/_id/vars**
_access: administer, people.management, owner_
Изменение доп. информации о  пользователе
_устарело_

**PUT user/_id/clientSettings**
_access: owner_
Изменение настроек клиента пользователя

**PUT user/_id/password**
_access: people.management, user (self)_
Изменение пароля. Админам менять пароли могут только они сами или другие админы.
<pre>
Request:
{
  password: md5(text)
}
</pre>
_устарело_


**PUT user/_id**
_access: administer, people.management_
Изменение данных пользователя
people.management не может устанавливать роль "admin", нельзя менять самому себе

Вообще people.management - читерский доступ. Можно нахоботить себе любой доступ кроме админского через фейковый аккаунт, например.
Поля для менеджера:
deleted
username
role
def_role
group
firstname
lastname

**PUT user/_id/projectrole**
_access: administer, people.management_
Установить проектную роль.
Если роль пустая - запись удаляется
<pre>
Request: {
  "project":
  "role":
}
</pre>

**POST user/_id/avatar?filename&size**
_access: owner_
Загрузить аватарку для юзера.
filename: исходное имя картинки (по нему можно формат определить)
size: размер данных в байтах.

Body: Бинарные данные картинки с аватаркой.


Проекты
=============

**GET project**
Возвращает список всех проектов
<pre>
Response: {
  class : 'projectList'
  items : [
    _project_object,
    _project_object
  ]
}
</pre>

**GET project/_id**
Возвращает описание проекта
<pre>
Response (_project_object):
{
  class      : 'project',
  id
  archived   : false,
  title
  shorttitle : ~abc,
  tagcolor   : ~color,
  created    : ~created_unix
}
</pre>

**GET project/_id/task**
Возвращает корневые задачи проекта.
<pre>
Response: {
  [
    _task_object,
    _task_object
  ]
}
</pre>

**GET project/_id/tasks?assignee=USER_ID&status=open&priority=1**
Возвращает все задачи проекта для данного юзера.

**POST project**
_access: administer, project.management_
Добавить новый проект
<pre>
Request: {
  title
}
Response: {
  "id" : 100
}
</pre>

**POST project/_id/task**
access: administer, task.management
Добавить новую задачу в указанный проект
<pre>
Request: {
  "title"
}
Response: {
  "id" : 100
}
</pre>

**PUT project/_id**
access: administer, project.management
Редактирование проекта
<pre>
Поля:
title
shorttitle
tagcolor
</pre>


Задачи
=============

**GET task/_id/oldnote**
Возвращает бинарник комментариев для задачи.

**GET task/<id>/oldcalendar**
Возвращает бинарник календаря для задачи.

**GET task?from&count**
Возвращает массив незавершенных (!!!) корневых (parentTask=0) тасков, незавершенных потому что завершенных очень много. Начиная с индекса from до индекса (from + count - 1) включительно.
По умолчанию from = 0, count = 100;
<pre>
Response:
{
    "from" :
    "to" :
    "items" : [
       // Таски как в GET/task/<id>.
    ]
}
</pre>

**GET task?assignee=USER_ID&status=STATUS**
Возвращает список тасков для конкретного пользователя со статусом STATUS (по умолчанию Open)

**GET task?search=title&text=SOME_TEXT**
<pre>
Response:
Задачи с указанным текстом в заголовке
</pre>

**GET task?search=timesheet&assignee=USER_ID&dayfrom&dayto**
Возвращает любые задачи для данного юзера, у которых расположение на календаре попадает в рамки (dayfrom; dayto)

**GET task/_id**
<pre>
Response  (_task_object):
{
    "project"  : айдишник проекта к которому относится таск. ~projid
    "type"     : "task, milestone, folder, issue, feature, testcase" (ENUM)
    "title"    : "My super task."
    "priority" : (TINYINT)
    "status"   : "open, inprogress, finished, reopened, closed, canceled" (ENUM)

    "assignee" : юзер, которому назначена задача. ~uid

    "parentTask" : айдишник родительской задачи или 0
    "sub-tasks" : [
        { "id" : 1000 },
        { "id" : 1001 }
        ]

    "attachments" : [
          {
              "url" : 1000,
              "filename" : 1000,
              "size" : 1000,
          }
        ]
    "estimatedEffortSeconds" : 3600
    "startDate" : Unix timestamp
    "duration"  : целое число дней
    "deadline"  : Unix timestamp (date)

    "rootComment"

    "created" : Unix Timestamp
    "createdby"
}
</pre>

**GET task/_id/tasks**
Возвращает массив вложенных подзадач.


**PUT task/_id**
_access: administer, task.management_
Изменить свойства существующей задачи.
<pre>
Поля для people.management:
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

Поля для assignee:
status

Поля для: access: tester
status
</pre>

**DELETE task/_id**
_access: administer, task.management_
Ответ: 204? No Content
Удалять можно только задачи верхнего уровня (не имеющие дочек)
TODO: очистка таймшитов и комментов


Комментарии
=============
Комментарии пишутся к задачам - для отображения дискуссии. Причем, само описание задачи - это автоматически созданный корневой комментарий, а все последующие комменты вкладываются в него.

**GET task/_id/comments**
<pre>
Response: {
    "id"
    "text"
    "created"
    "createdby"
    "modified"
    "modifiedby"
    "childes": []
}
</pre>

**POST task/_id/comment**
_access: comment.management, assignee, tester_
<pre>
Request: {
  "text": "",
  "parent": 100,
}
Response: {
  "id" : 100,
}
</pre>

**PUT comment/_id**
_access: administer, comment.management, owner_

Здесь "text" - это форматированный HTML текст с простейшими тэгами.
При редактировании комментария у него автоматически апдейтятся поля кем и когда изменено.

Поля:
text


Календари
=============
Календарь есть общий для всех сотрудников (где отмечаются праздники и отклонения от дефолтного календаря (каждая суббота и воскресенье - выходной)) и есть персональные календари, где отмечаются отклонения от общего календаря для каждого юзера в отдельности.

**GET calendar?from=15275&count=100**
Запрос на получение общего календаря за указанный интервал дат.
<pre>
Response: [
  { "day" : 15275, "kind" : "workday|dayoff" }
]
</pre>

**GET user/_id/calendar?from=15275&count=100**
Получить отличия в календаря юзера от дефолтного календаря.

**PUT calendar?day=15275**
access: administer
Запрос на создание исключения из дефолтного календаря.
Body: {
  "kind" : "workday|dayoff"
}

**PUT user/<id>/calendar**
_access: administer, people.management_
Запрос на создание исключения из календаря юзера.

<pre>
Body: {
  "day" : 15275,
  "kind" : "workday|dayoff"
}
</pre>

**DELETE user/_id/calendar?day=15275**
access: administer, people.management
Запрос на удаление исключения из календаря юзера.
