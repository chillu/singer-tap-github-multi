SELECT id, type, created_at, repo.name, actor.login, payload
FROM (
  TABLE_DATE_RANGE(
    [githubarchive:day.],
    TIMESTAMP('2018-08-14'),
    TIMESTAMP('2018-08-15')
  )
)
WHERE repo.name IN (
    'myorg/myrepo'
)