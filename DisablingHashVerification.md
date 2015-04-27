With Layar 4.0, Layar has abondoned the developer ID and developer key and thereby disabled the "developerHash" verification method, for _new_ layers only. PorPOISe up to 0.71 by default tries to verify the developer hash and will not accept requests with an invalid hash. If you, for some reason, want to host new layers on a PorPOISe version older than 1.0 you need to manually disable hash verification.

To do this, open the file `layer.class.php`. Near the top you will find a line that looks like
```
    const VERIFY_HASH = TRUE;
```
Replace `TRUE` by `FALSE`, save the file and you are good to go.

Note: this instruction only applies to PorPOISe versions up to 1.0rc1. PorPOISe 1.0 and up do not do hash verification any more.