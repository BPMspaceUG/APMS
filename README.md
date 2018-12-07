# APMS
**APMS** means **A**gile **P**rocess **M**anagement **S**ystem

# Requirements

## Database Structure
- Tables must have only ONE Primary Column (no split primarykeys allowed)
- Tablename must contain only this Letters: "A-Z", "a-z" and "_"
- The columnname "state_id" is reserved and can NOT be used

## Compile the Javascript
The javascript is generated via Typescript. Compile the TS-File in the generator parts directory with the following command:

```
tsc .\muster.ts -w --lib 'ES2015, DOM' --target ES2015
```
