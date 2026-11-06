import os,sys
os.environ['MODEL_CHECKSUM_DISABLED']='1'
from PIL import Image
import onnxruntime as ort
from rembg import remove,new_session
src,dst=sys.argv[1:3]
so=ort.SessionOptions(); so.enable_cpu_mem_arena=False; so.intra_op_num_threads=2; so.execution_mode=ort.ExecutionMode.ORT_SEQUENTIAL
s=new_session('isnet-general-use',sess_opts=so)
im=Image.open(src).convert('RGB'); im.thumbnail((1200,1200))
remove(im,session=s).save(dst); print('ok',dst)
