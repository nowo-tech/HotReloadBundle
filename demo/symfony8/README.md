# Console Debug Bundle Demo (Symfony 8)

FrankenPHP demo app to validate `RedirectToRefererTrait` and `SafeForwardTrait`.

## Usage

```bash
make up
make install
```

Open `http://localhost:8011`.

## Included dev stack

- Symfony Web Profiler
- Symfony debug mode (`APP_DEBUG=1` in `.env.example`)
- `nowo-tech/twig-inspector-bundle`

## Key routes

- `/` demo home
- `/forward` tests `SafeForwardTrait`
- `/back` tests `RedirectToRefererTrait`
